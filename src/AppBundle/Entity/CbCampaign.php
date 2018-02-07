<?php

namespace AppBundle\Entity;

use Rbl\CouchbaseBundle\Base\CbBaseObject;

class CbCampaign extends CbBaseObject
{

    const STATUS_READY = 'ready';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function __construct()
    {
        parent::__construct();
        $this->setCreated(new \DateTime());
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setStatus(string $status)
    {
        $this->set('status', $status);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getStatus() : string
    {
        return $this->get('status');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setEnabled(bool $enabled)
    {
        $this->set('enabled', $enabled);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getEnabled() : bool
    {
        return $this->get('enabled');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setClientDomain(string $clientDomain)
    {
        $this->set('clientDomain', $clientDomain);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getClientDomain() : string
    {
        return $this->get('clientDomain');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setCreated(\DateTime $time = null)
    {
        if(!$time){
            $time = new \DateTime();
            $time->format('U');
        }

        $this->set('created', $time->getTimestamp());

    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getCreated() : \DateTime
    {
        $ret = new \DateTime();
        $unixtimestamp = $this->get('created');
        $ret->setTimestamp($unixtimestamp);
        return $ret;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setNeedPosts(int $needPosts)
    {
        $this->set('needPosts', $needPosts);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getNeedPosts() : int
    {
        return $this->get('needPosts');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setAdditionalKeysPercentage(float $additionalKeysPercentage)
    {
        $this->set('additionalKeysPercentage', $additionalKeysPercentage);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getAdditionalKeysPercentage() : float
    {
        return $this->get('additionalKeysPercentage');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setPostPeriodDays(int $postPeriodDays)
    {
        $this->set('postPeriodDays', $postPeriodDays);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getPostPeriodDays() : int
    {
        return $this->get('postPeriodDays');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setNextPostTime(\DateTime $time = null)
    {
        $this->set('nextPostTime', $time->getTimestamp());
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getNextPostTime() : \DateTime
    {
        $ret = new \DateTime();
        $unixtimestamp = $this->get('nextPostTime');
        $ret->setTimestamp($unixtimestamp);
        return $ret;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setPosted(int $posted)
    {
        $this->set('posted', $posted);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getPosted() : int
    {
        return $this->get('posted');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setBlogs(array $blogs)
    {
        $this->set('blogs', $blogs);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getBlogs() : array
    {
        return $this->get('blogs');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getBlogForPostingId() : string
    {
        $blogs = $this->getBlogs();

        $resultArray = array_keys($blogs, min($blogs));

        return array_shift($resultArray);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function incrementPostsForBlog(string $blogId){
        $blogs = $this->get('blogs');
        if(isset($blogs[$blogId])) {
            $blogs[$blogId]++;
        }
        $this->setBlogs($blogs);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setErrors(int $errors)
    {
        $this->set('errors', $errors);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getErrors() : int
    {
        return $this->get('errors');
    }


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
}
