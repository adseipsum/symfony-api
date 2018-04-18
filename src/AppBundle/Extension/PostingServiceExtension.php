<?php
namespace AppBundle\Extension;

use Rbl\CouchbaseBundle\Entity\CbCampaign;
use Rbl\CouchbaseBundle\Entity\CbTask;
use Rbl\CouchbaseBundle\Model\TaskModel;
use Rbl\CouchbaseBundle\Model\BlogModel;
use Rbl\CouchbaseBundle\Model\CampaignModel;
use Rbl\CouchbaseBundle\Model\TextGenerationResultModel;
use Rbl\CouchbaseBundle\CouchbaseService;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Krombox\OAuth2\Client\Provider\WordPress;

class PostingServiceExtension
{
    protected $cb;
    protected $taskModel;
    protected $taskObject;
    protected $textModel;
    protected $blogModel;

    const THIS_SERVICE_KEY = 'pst';
    const CAMPAIGN_MANAGER_ROUTING_KEY = 'srv.cmpmanager.v1';

    const WP_POSTS_PATH = '/wp-json/wp/v2/posts/';
    const WP_MEDIA_PATH = '/wp-json/wp/v2/media/';
    const WP_BACKLINK = 'oob';

    public function __construct(CouchbaseService $cb, $amqp)
    {
        $this->cb = $cb;
        $this->campaignModel = new CampaignModel($this->cb);
        $this->taskModel = new TaskModel($this->cb);
        $this->blogModel = new BlogModel($this->cb);
        $this->textModel = new TextGenerationResultModel($this->cb);
        $this->textModel->setBucket($this->cb->getBucketForType('TextGenerationResult'));

        $this->amqp = $amqp;
    }

    /**
     * @return bool
     */
    public function postToBlog(){

        $campaignObject = $this->campaignModel->get($this->taskObject->getCampaignId());

        $bodyObject = $this->textModel->getSingle($this->taskObject->getBodyId());
        $blogObject = $this->blogModel->get($this->taskObject->getBlogId());
        $headerObject = $this->textModel->getSingle($this->taskObject->getHeaderId());
        $seoTitleObject = $this->textModel->getSingle($this->taskObject->getSeoTitleId());
        $seoDescriptionObject = $this->textModel->getSingle($this->taskObject->getSeoDescriptionId());

        if($campaignObject->getType() == CbCampaign::TYPE_BACKLINKED) {
            $bodyText = $this->setTagMore($bodyObject->getBacklinkedText());
        }else{
            $bodyText = $this->setTagMore($bodyObject->getText());
        }

        $WPRequestBody = array(
            'title' => $headerObject->getText(),
            'content' => $bodyText,
            'status' => 'publish',
            'ping_status' => 'closed',
            'type' => 'post',
            'categories' => array(1),
            'featured_media' => $this->taskObject->getImageId(),
            'meta' => array(
                '_yoast_wpseo_metadesc' => $seoDescriptionObject->getText(),
                '_yoast_wpseo_title' => $seoTitleObject->getText()
            )
        );

        $provider = new Wordpress([
            'clientId'                => $blogObject->getClientId(),
            'clientSecret'            => $blogObject->getClientSecret(),
            'redirectUri'             => self::WP_BACKLINK,
            'domain'                  => $blogObject->getDomainName()
        ]);

        try {
            $accessToken = $provider->getAccessToken('password', [
                'username' => $blogObject->getPostingUserLogin(),
                'password' => $blogObject->getPostingUserPassword()
            ]);

            $options['body'] = json_encode($WPRequestBody);
            $options['headers']['Content-Type'] = 'application/json;charset=UTF-8';
            $options['headers']['access_token'] = $accessToken->getToken();

            $request = $provider->getAuthenticatedRequest(
                'POST',
                $blogObject->getDomainName() . self::WP_POSTS_PATH,
                $accessToken->getToken(),
                $options
            );

            $response = $provider->getResponse($request);

            if(!isset($response['code'])){
                $blogObject->setLastPostDate(new \DateTime());
                $blogObject->setLastPostId($response['id']);

                if(isset($response['link'])){
                    $this->taskObject->setPostLink($response['link']);
                    $this->taskModel->upsert($this->taskObject);
                }
            }else{
                $blogObject->setLastErrorMessage($response['code']);
            }
            $this->blogModel->upsert($blogObject);

            if($this->updateMedia($provider, $accessToken)){
                return true;
            }

        } catch (IdentityProviderException $e) {
            echo $e->getMessage();
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function updateMedia($provider, $accessToken){

        $blogObject = $this->blogModel->get($this->taskObject->getBlogId());
        $imageAlt = $this->textModel->getSingle($this->taskObject->getImageAltId());

        $WPRequestBody = array(
            'alt_text' => $imageAlt->getText(),
            'description' => $imageAlt->getText(),
            'title' => $imageAlt->getText(),
            'caption' => $imageAlt->getText()
        );

        try {
            $options['body'] = json_encode($WPRequestBody);
            $options['headers']['Content-Type'] = 'application/json;charset=UTF-8';
            $options['headers']['access_token'] = $accessToken->getToken();

            $request = $provider->getAuthenticatedRequest(
                'POST',
                $blogObject->getDomainName() . self::WP_MEDIA_PATH .  $this->taskObject->getImageId(),
                $accessToken->getToken(),
                $options
            );

            $response = $provider->getResponse($request);

            if(isset($response['id'])){
                return true;
            }

        } catch (IdentityProviderException $e) {
            echo $e->getMessage();
        }

        return false;
    }

    /**
     * @param object $msg
     * @return void
     */
    public function processMessage($msg){
        $message = json_decode($msg->getBody());
        $idString = explode('::', $message->taskId);
        $taskId = $idString[1];

        $this->taskObject = $this->taskModel->get($taskId);

        if($this->taskObject){
            if($this->postToBlog()) {
                $this->sendCompletePostingMessage($this->taskObject->getObjectId(), $message->responseRoutingKey);
            }else{
                $msg = array('taskId' => implode( '::', array(self::THIS_SERVICE_KEY, $this->taskObject->getObjectId(), CbTask::STATUS_FAILED)));
                $this->amqp->publish(json_encode($msg), self::CAMPAIGN_MANAGER_ROUTING_KEY);
            }
        }
    }

    /**
     * @param string $taskId
     * @return void
     */
    protected function sendCompletePostingMessage($taskId, $responseRoutingKey){
        $msg = array(
            'taskId' => implode( '::', array(self::THIS_SERVICE_KEY, $taskId, CbTask::STATUS_TEXT_POST)),
        );

        $this->amqp->publish(json_encode($msg), $responseRoutingKey);
    }

    protected function setTagMore($bodyText){
        $position = strpos($bodyText, '</p>') + 4;
        return substr_replace($bodyText, '<!--more-->', $position, 0);
    }

}