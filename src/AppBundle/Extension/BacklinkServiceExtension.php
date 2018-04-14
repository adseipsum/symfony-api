<?php
namespace AppBundle\Extension;

use Rbl\CouchbaseBundle\Entity\CbTask;
use Rbl\CouchbaseBundle\Model\TaskModel;
use Rbl\CouchbaseBundle\Model\BlogModel;
use Rbl\CouchbaseBundle\Model\CampaignModel;
use Rbl\CouchbaseBundle\Model\TextGenerationResultModel;
use Rbl\CouchbaseBundle\CouchbaseService;

class BacklinkServiceExtension
{
    protected $cb;
    protected $taskModel;
    protected $taskObject;
    protected $textModel;
    protected $blogModel;
    protected $campaignModel;
    protected $campaignObject;
    protected $endOfText = false;
    protected $link = array();
    protected $additionalKeywords = array(
        'Read more',
        'Get more information on this',
        'Full text here',
        'Click to read more',
        'See full list',
        'The full list here',
        'Click here',
        'Get more information here ',
        'Click to read more',
        'Get more information here',
        'Click to read more about the treatment',
        'More information here',
        'Click to see more',
        'Read more about the, here'
    );

    const THIS_SERVICE_KEY = 'bln';
    const POST_MANAGER_ROUTING_KEY = 'srv.postmanager.v1';
    const CAMPAIGN_MANAGER_SERVICE_ROUTING_KEY = 'srv.cmpmanager.v1';

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
    private function generateBacklink(){
        $blogObject = $this->blogModel->get($this->taskObject->getBlogId());

        $mainDomain = $this->campaignObject->getMainDomain();
        $postMainDomainLinks = $this->campaignObject->getPostMainDomainLinks();
        $mainLinksPosted = $this->campaignObject->getMainLinksPosted();
        $postSubLinks = $this->campaignObject->getPostSubLinks();
        $subLinksPosted = $this->campaignObject->getSubLinksPosted();


        $subLinks = $this->campaignObject->getSubLinks();

        //Great random occurrence 1
        if($this->getRandomBoolean() && $postMainDomainLinks > $mainLinksPosted && !in_array($mainDomain, $blogObject->getMainDomainLinksPosted())){
            /* --- MAIN LINK --- */

            $mainPostedPercentage = $mainLinksPosted * 100 / $postMainDomainLinks;

            $keywords = $this->campaignObject->getMainKeywords();
            $this->link = array('href' =>  $mainDomain, 'name' =>  $keywords[array_rand($keywords)]);

            //Great random occurrence 2
            if($this->getRandomBoolean() && 100 - $mainPostedPercentage > intval($this->campaignObject->getAdditionalKeysPercentage())){
                $this->endOfText = true;
                $this->link = array('href' =>  $mainDomain, 'name' =>  $this->additionalKeywords[array_rand($this->additionalKeywords)]);
            }

            $this->campaignObject->getMainLinksPosted($this->campaignObject->getMainLinksPosted() + 1);

            //save domain in the list of posted main domain links for a blog
            $this->blogModel->updateMainDomainLinksPosted($blogObject, $this->campaignObject->getMainDomain());
            $this->blogModel->upsert($blogObject);

            return true;
        }elseif($subLinks && $postSubLinks > $subLinksPosted){
            /* --- SUB LINK --- */

            $randomSubLink = $subLinks[array_rand($subLinks)];
            $subLinksPostedPercentage = $subLinksPosted * 100 / $postSubLinks;

            $keywords = explode(',', $randomSubLink['subLinkKeywords']);
            $this->link = array('href' =>  $randomSubLink['subLink'], 'name' =>  $keywords[array_rand($keywords)]);

            //Great random occurrence 2
            if($this->getRandomBoolean() && 100 - $subLinksPostedPercentage > intval($randomSubLink['subAdditionalKeywordsPercentage'])){
                $this->endOfText = true;
                $this->link = array('href' =>  $randomSubLink['subLink'], 'name' =>  $this->additionalKeywords[array_rand($this->additionalKeywords)]);
            }
            $this->campaignObject->setSubLinksPosted($this->campaignObject->getSubLinksPosted() + 1);

            return true;
        }

        $this->campaignModel->upsert($this->campaignObject);

        return false;
    }

    /**
     * @return bool
     */
    public function insertBacklink(){
        $bodyObject = $this->textModel->getSingle($this->taskObject->getBodyId());
        $link = ' <a href="http://' . $this->link['href'] . '" target="_blank">' . $this->link['name'] . '</a> ';
        $text = $bodyObject->getText();

        if($this->endOfText){
            $text = $text . $link;
        }else{
            $offset = rand(strlen($text) * 0.2, strlen($text) * 0.8);
            $position = strpos($text, ' ', $offset);
            $text = substr_replace($text, $link, $position, 0);
        }

        $bodyObject->setBacklinkedText($text);
        $this->textModel->upsert($bodyObject);

        return true;
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
            $this->campaignObject = $this->campaignModel->get($this->taskObject->getCampaignId());


            if($this->generateBacklink()) {
                $this->insertBacklink();
                $this->sendCompletePostingMessage($this->taskObject->getObjectId(), $message->responseRoutingKey);
            }else{
                $msg = array('taskId' => implode( '::', array(self::THIS_SERVICE_KEY, $this->taskObject->getObjectId(), CbTask::STATUS_FAILED)));
                $this->amqp->publish(json_encode($msg), self::CAMPAIGN_MANAGER_SERVICE_ROUTING_KEY);
            }
        }
    }

    /**
     * @param string $taskId
     * @return void
     */
    protected function sendCompletePostingMessage($taskId, $responseRoutingKey){
        $msg = array(
            'taskId' => implode( '::', array(self::THIS_SERVICE_KEY, $taskId, CbTask::STATUS_BACKLINK_INSERT)),
        );

        $this->amqp->publish(json_encode($msg), $responseRoutingKey);
    }

    protected function getRandomBoolean(){
        return rand(0,1) == 1;
    }


}