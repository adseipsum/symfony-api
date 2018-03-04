<?php
namespace AppBundle\Extension;

use AppBundle\Entity\CbCampaign;
use AppBundle\Entity\CbTask;
use AppBundle\Entity\CbBlog;
use AppBundle\Repository\TaskModel;
use AppBundle\Repository\BlogModel;
use AppBundle\Repository\CampaignModel;
use AppBundle\Repository\TextGenerationResultModel;
use Rbl\CouchbaseBundle\CouchbaseService;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Krombox\OAuth2\Client\Provider\Wordpress;
use AppBundle\Entity\CbTextGenerationResult;

class PostingServiceExtension
{
    protected $cb;
    protected $taskModel;

    /* @var  $taskObject CbTask */
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

        /* @var  $campaignObject CbCampaign */
        $campaignObject = $this->campaignModel->get($this->taskObject->getCampaignId());

        /* @var  $bodyObject CbTextGenerationResult */
        $bodyObject = $this->textModel->getSingle($this->taskObject->getBodyId());

        /* @var  $blogObject CbBlog */
        $blogObject = $this->blogModel->get($this->taskObject->getBlogId());

        /* @var  $headerObject CbTextGenerationResult */
        $headerObject = $this->textModel->getSingle($this->taskObject->getHeaderId());

        /* @var  $seoTitleObject CbTextGenerationResult */
        $seoTitleObject = $this->textModel->getSingle($this->taskObject->getSeoTitleId());

        /* @var  $seoDescriptionObject CbTextGenerationResult */
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
            'type' => 'post',
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

        /* @var  $blogObject CbBlog */
        $blogObject = $this->blogModel->get($this->taskObject->getBlogId());

        /* @var  $imageAlt CbTextGenerationResult */
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