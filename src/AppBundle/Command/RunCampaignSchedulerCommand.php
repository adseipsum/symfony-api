<?php

namespace AppBundle\Command;

use Rbl\CouchbaseBundle\Entity\CbTask;
use Rbl\CouchbaseBundle\Model\TaskModel;
use Rbl\CouchbaseBundle\Model\CampaignModel;
use Rbl\CouchbaseBundle\Entity\CbCampaign;
use Rbl\CouchbaseBundle\Model\BlogModel;
use Exception;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCampaignSchedulerCommand extends ContainerAwareCommand
{
    const THIS_SERVICE_KEY = 'csd';
    const RESPONCE_ROUTING_KEY = 'srv.cmpmanager.v1';
    const POST_MANAGER_ROUTING_KEY = 'srv.postmanager.v1';

    protected function configure()
    {
        $this->setName('campaign-scheduler:run')->setDescription('Process campaigns for posting');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //check if any campaigns ready for posting
        //search from list of the campaign blogs for one we can post on and lock it
        //send message to Post Manager with new task
        try {
            $cb = $this->getContainer()->get('couchbase.connector');

            $campaignModel = new CampaignModel($cb);
            $campaignObject = $campaignModel->getCampaignForPosting();

            //stop if none of campaigns ready for posting
            if(!$campaignObject){
                $output->writeln('No ready campaigns to processed.');
                return false;
            }

            $blogModel = new BlogModel($cb);
            $blogs = $campaignObject->getBlogs();

            //make sure we will start from blogs with lesser amount of posts
            asort($blogs);

            foreach($blogs as $blogId => $counter){
                $blogObject = $blogModel->get($blogId);

                if($blogObject && $blogObject->getEnabled() && $blogObject->isBlogReadyForPosting()){

                    if($campaignObject->getType() == CbCampaign::TYPE_BACKLINKED
                        && (in_array($campaignObject->getMainDomain(), $blogObject->getMainDomainLinksPosted()) || $blogObject->getLastTypePosted() == CbCampaign::TYPE_BACKLINKED)){
                        continue;
                    }

                    if(!$blogModel->lockBlogForPosting($blogObject)){
                        $blogObject->setLocked(false);
                        $blogModel->upsert($blogObject);
                        continue;
                    }
                    echo $blogObject->getObjectId() . ' locked';
                    break;
                }else{
                    continue;
                }
            }

            if(!isset($blogObject)){
                $output->writeln('No blogs ready for posting for campaign ' . $campaignObject->getObjectId());
                $campaignObject->setNextPostTime(new \DateTime('now + 600 seconds'));
                $campaignModel->upsert($campaignObject);
                return false;
            }

            $taskModel = new TaskModel($cb);
            $taskId = $taskModel->createTask($blogObject->getObjectId(), $campaignObject->getObjectId());

            $generatedTaskId = implode('::', array(self::THIS_SERVICE_KEY, $taskId, CbTask::STATUS_NEW));

            $msg = array(
                'taskId' => $generatedTaskId,
                'responseRoutingKey' => self::RESPONCE_ROUTING_KEY
            );

            //send message to post manager service
            $amqp = $this->getContainer()->get('old_sound_rabbit_mq.campaign_scheduler_producer');
            $amqp->publish(json_encode($msg), self::POST_MANAGER_ROUTING_KEY);

            $campaignObject->setStatus(CbCampaign::STATUS_PROCESSING);
            $campaignModel->upsert($campaignObject);

            $output->writeln($campaignObject->getObjectId() . ' processed');

        } catch (Exception $e) {
            $output->writeln($e->getMessage());
        }
    }
}
