<?php
namespace AppBundle\Extension;

use Rbl\CouchbaseBundle\Entity\CbTask;
use Rbl\CouchbaseBundle\Model\TaskModel;
use Rbl\CouchbaseBundle\Entity\CbBlog;
use Rbl\CouchbaseBundle\Model\BlogModel;
use Rbl\CouchbaseBundle\CouchbaseService;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Krombox\OAuth2\Client\Provider\WordPress;

class ImagePostingServiceExtension
{
    protected $cb;
    protected $taskModel;

    /** @var  $taskObject CbTask */
    protected $taskObject;

    protected $blogModel;

    /** @var  $blogObject CbBlog */
    protected $blogObject;

    const THIS_SERVICE_KEY = 'pst';
    const CAMPAIGN_MANAGER_ROUTING_KEY = 'srv.cmpmanager.v1';

    const WP_RESOURCE_PATH = '/wp-json/wp/v2/media/';
    const IMAGE_SOURCE_URL = 'http://88.99.193.160:9879/7v5xr4hCrW36-ypG/?';
    const WP_BACKLINK = 'oob';

    public function __construct(CouchbaseService $cb, $amqp)
    {
        $this->cb = $cb;
        $this->taskModel = new TaskModel($this->cb);
        $this->blogModel = new BlogModel($this->cb);
        $this->amqp = $amqp;
    }

    /**
     * @return bool
     */
    public function postImageToBlog(){

        $provider = new Wordpress([
            'clientId'                => $this->blogObject->getClientId(),
            'clientSecret'            => $this->blogObject->getClientSecret(),
            'redirectUri'             => self::WP_BACKLINK,
            'domain'                  => $this->blogObject->getDomainName()
        ]);

        try {
            $accessToken = $provider->getAccessToken('password', [
                'username' => $this->blogObject->getPostingUserLogin(),
                'password' => $this->blogObject->getPostingUserPassword()
            ]);

            $imageRequest = array(
                'blog_id' => $this->blogObject->getObjectId(),
                'watermark' => $this->blogObject->getDomainName()
            );

            $image = file_get_contents(self::IMAGE_SOURCE_URL . http_build_query($imageRequest));
            $generatedFileName = 'TMP_IMG_' . uniqid();

            file_put_contents(sys_get_temp_dir() . '/' . $generatedFileName . '.jpg', $image);

            $options['body'] = $image;
            $options['headers']['content-type'] = 'image/jpg';
            $options['headers']['content-disposition'] = 'attachment; filename="' . sys_get_temp_dir() . '/' . $generatedFileName . '.jpg';
            $options['headers']['access_token'] = $accessToken->getToken();

            $request = $provider->getAuthenticatedRequest(
                'POST',
                $this->blogObject->getDomainName() . self::WP_RESOURCE_PATH,
                $accessToken->getToken(),
                $options
            );

            $response = $provider->getResponse($request);

            if(isset($response['code'])){
                $this->blogObject->setLastErrorMessage($response['code']);
                $this->blogModel->upsert($this->blogObject);
            }

            unlink(sys_get_temp_dir() . '/' . $generatedFileName . '.jpg');

            if(isset($response['id'])) {
                return $response['id'];
            }

        } catch (\Exception $e) {
            $this->blogObject->setLastErrorMessage($e->getMessage());
            $this->blogModel->upsert($this->blogObject);
        }

        return false;
    }

    /**
     * @param object $msg
     * @return void
     */
    public function processMessage($msg){
        $message = json_decode($msg->getBody());

        echo "received message: \n";
        var_dump($message);

        $idString = explode('::', $message->taskId);
        $taskId = $idString[1];
 
        $this->taskObject = $this->taskModel->get($taskId);

        if($this->taskObject){
            $this->blogObject = $this->blogModel->get($this->taskObject->getBlogId());
            $imageId = $this->postImageToBlog();
            if($imageId) {
                $this->sendCompleteImagePostingMessage($this->taskObject->getObjectId(), $imageId, $message->responseRoutingKey);
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
    private function sendCompleteImagePostingMessage($taskId, $imageId, $responseRoutingKey){
        $msg = array(
            'taskId' => implode( '::', array(self::THIS_SERVICE_KEY, $taskId, CbTask::STATUS_IMAGE_POST)),
            'imageId' => $imageId
        );

        echo "send message: \n";
        var_dump($responseRoutingKey);
        var_dump($msg);

        $this->amqp->publish(json_encode($msg), $responseRoutingKey);
    }

}