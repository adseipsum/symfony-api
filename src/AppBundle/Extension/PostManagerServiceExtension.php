<?php
namespace AppBundle\Extension;

use Rbl\CouchbaseBundle\Entity\CbCampaign;
use Rbl\CouchbaseBundle\Entity\CbTask;
use Rbl\CouchbaseBundle\Model\TaskModel;
use Rbl\CouchbaseBundle\Model\CampaignModel;
use Rbl\CouchbaseBundle\Model\BlogModel;
use Rbl\CouchbaseBundle\Model\TextGenerationResultModel;
use Rbl\CouchbaseBundle\CouchbaseService;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Krombox\OAuth2\Client\Provider\Wordpress;

class PostManagerServiceExtension
{
    protected $cb;
    protected $amqp;
    protected $taskModel;
    protected $textModel;
    protected $backlinked = false;

    const THIS_SERVICE_KEY = 'pms';
    const TEXT_GENERATION_RESULT_KEY = 'tgrst';

    const TEXT_GENERATION_ROUTING_KEY = 'prod-satteliter.q.srv-txtgen.v2';
    const TEXT_DPN_GENERATION_ROUTING_KEY = 'prod-satteliter.q.srv-txtderr.v1';
    const BACKLINK_INSERT_SERVICE_ROUTING_KEY = 'srv.backlink.v1';
    const IMAGE_POSTING_SERVICE_ROUTING_KEY = 'srv.imgposting.v1';
    const POSTING_SERVICE_ROUTING_KEY = 'srv.posting.v1';
    const CAMPAIGN_MANAGER_SERVICE_ROUTING_KEY = 'srv.cmpmanager.v1';
    const RESPONSE_ROUTING_KEY = 'srv.postmanager.v1';

    const TEXT_GENERATED = 'text';
    const TEXT_DPN_GENERATED = 'textdpn';

    const HEADER_MAX_LENGTH = 70;

    public function __construct(CouchbaseService $cb, $amqp)
    {
        $this->cb = $cb;
        $this->amqp = $amqp;
        $this->taskModel = new TaskModel($this->cb);
        $this->textModel = new TextGenerationResultModel($this->cb);
    }

    public function processMessage($msg){
        $message = json_decode($msg->getBody());
        $idString = explode('::', $message->taskId);
        $taskId = $idString[1];
        $statusKey = $idString[2];

        $textConfig = array(
            'type' => 'template'
        );

        echo "received message: \n";
        var_dump($message);

        switch($statusKey){
            case CbTask::STATUS_NEW:
                $textConfig['paragraph'] = true;
                $textConfig['paragraphSize'] = array(200, 250);
                $textConfig['type'] = 'random';
                $textConfig['size'] = 1500;
                $textConfig['ngram'] = array(
                    'apply' => true,
                    'mode' => 'insert',
                    'framesize' => 4,
                    'probability' => 0.6
                );

                //send message to generate text
                $this->sendMessage(self::TEXT_GENERATION_ROUTING_KEY, $taskId,CbTask::STATUS_BODY_GEN, $textConfig);
                break;
            case CbTask::STATUS_BODY_GEN:
                $this->taskModel->updateTask($taskId, array('setStatus' => CbTask::STATUS_BODY_GEN, 'setBodyId' => $message->resultKey));

                $textConfig['templateId'] = 'tpl-57';
                $textConfig['inputTextId'] = $message->resultKey;
                $textConfig['mainSubject'] = 'Subject2';

                $this->sendMessage(self::TEXT_GENERATION_ROUTING_KEY, $taskId,CbTask::STATUS_HEADER_GEN, $textConfig);
                break;
            case CbTask::STATUS_HEADER_GEN:

                //if generated header length more than allowed limit
                $textObject = $this->textModel->get($message->resultKey);
                if(strlen($textObject->getText()) > self::HEADER_MAX_LENGTH){
                    $this->taskModel->updateTask($taskId, array('setStatus' => CbTask::STATUS_NEW));
                    $this->sendMessage(self::RESPONSE_ROUTING_KEY, $taskId,CbTask::STATUS_NEW);
                    break;
                }

                $this->taskModel->updateTask($taskId, array('setStatus' => CbTask::STATUS_HEADER_GEN, 'setHeaderId' => $message->resultKey));

                $textConfig['templateId'] = 'tpl-58';
                $taskObject = $this->taskModel->get($taskId);
                $textConfig['inputTextId'] = $taskObject->getBodyId();
                $textConfig['mainSubject'] = 'Subject2';

                $this->sendMessage(self::TEXT_GENERATION_ROUTING_KEY, $taskId,CbTask::STATUS_SEO_TITLE_GEN, $textConfig);
                break;
            case CbTask::STATUS_SEO_TITLE_GEN:
                $this->taskModel->updateTask($taskId, array('setStatus' => CbTask::STATUS_SEO_TITLE_GEN, 'setSeoTitleId' => $message->resultKey));

                $textConfig['type'] = 'description';
                $taskObject = $this->taskModel->get($taskId);
                $textConfig['inputTextId'] = $taskObject->getBodyId();
                $textConfig['size'] = 160;

                $this->sendMessage(self::TEXT_DPN_GENERATION_ROUTING_KEY, $taskId,CbTask::STATUS_SEO_DESCRIPTION_GEN, $textConfig);
                break;
            case CbTask::STATUS_SEO_DESCRIPTION_GEN:
                $this->taskModel->updateTask($taskId, array('setStatus' => CbTask::STATUS_SEO_DESCRIPTION_GEN, 'setSeoDescriptionId' => $message->resultKey));

                $textConfig['type'] = 'imagealt';
                $taskObject = $this->taskModel->get($taskId);
                $textConfig['inputTextId'] = $taskObject->getBodyId();
                $textConfig['size'] = 160;

                $this->sendMessage(self::TEXT_DPN_GENERATION_ROUTING_KEY, $taskId,CbTask::STATUS_IMAGE_ALT_GEN, $textConfig);
                break;
            case CbTask::STATUS_IMAGE_ALT_GEN:
                $this->taskModel->updateTask($taskId, array('setStatus' => CbTask::STATUS_IMAGE_ALT_GEN, 'setImageAltId' => $message->resultKey));

                $taskObject = $this->taskModel->get($taskId);
                $campaignModel = new CampaignModel($this->cb);
                $campaignObject = $campaignModel->get($taskObject->getCampaignId());

                if($campaignObject->getType() == CbCampaign::TYPE_REGULAR){
                    $this->sendMessage(self::IMAGE_POSTING_SERVICE_ROUTING_KEY, $taskId, CbTask::STATUS_IMAGE_POST);
                }else{
                    $this->sendMessage(self::BACKLINK_INSERT_SERVICE_ROUTING_KEY, $taskId, CbTask::STATUS_BACKLINK_INSERT);
                }

                break;
            case CbTask::STATUS_BACKLINK_INSERT:

                $taskObject = $this->taskModel->get($taskId);
                $taskObject->setBacklinked(true);
                $this->taskModel->upsert($taskObject);

                $this->taskModel->updateTask($taskId, array('setStatus' => CbTask::STATUS_BACKLINK_INSERT));
                //send message to image posting service
                $this->sendMessage(self::IMAGE_POSTING_SERVICE_ROUTING_KEY, $taskId, CbTask::STATUS_IMAGE_POST);
                break;
            case CbTask::STATUS_IMAGE_POST:
                $this->taskModel->updateTask($taskId, array('setStatus' => CbTask::STATUS_IMAGE_POST, 'setImageId' => $message->imageId));
                //send message to posting service
                $this->sendMessage(self::POSTING_SERVICE_ROUTING_KEY, $taskId, CbTask::STATUS_TEXT_POST);
                break;
            case CbTask::STATUS_TEXT_POST:
                $this->taskModel->updateTask($taskId, array('setStatus' => CbTask::STATUS_TEXT_POST));

                $taskObject = $this->taskModel->get($taskId);

                if($taskObject->getBacklinked()){
                    $blogModel = new BlogModel($this->cb);
                    $blogObject = $blogModel->get($taskObject->getBlogId());
                    $blogObject->setLastBacklinkedPostId($blogObject->getLastPostId());
                    $blogModel->upsert($blogObject);
                }

                //send message to Campaign Manager
                $this->sendMessage(self::CAMPAIGN_MANAGER_SERVICE_ROUTING_KEY, $taskId, CbTask::STATUS_COMPLETED);
                break;
            default:
                break;
        }
    }

    private function sendMessage($routingKey, $taskId, $statusKey, $textConfig = array()){
        $generatedTaskId = implode('::', array(self::THIS_SERVICE_KEY, $taskId, $statusKey));

        $msg = array(
            'taskId' => $generatedTaskId,
            'responseRoutingKey' => self::RESPONSE_ROUTING_KEY,
        );

        //if something needs to be saved in CB
        if($textConfig){
            $msg['textconfig'] = $textConfig;
            $generatedResultKeyId = implode('::', array(self::TEXT_GENERATION_RESULT_KEY, $taskId, $statusKey));
            $msg['resultKey'] = $generatedResultKeyId;
        }

        echo "send message: \n";
        var_dump($routingKey);
        var_dump($msg);
        $this->amqp->publish(json_encode($msg), $routingKey);
    }

}