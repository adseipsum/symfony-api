<?php

namespace AppBundle\Entity;

use Rbl\CouchbaseBundle\Base\CbBaseObject;

class CbTask extends CbBaseObject
{

    const STATUS_NEW = 'new';
    const STATUS_BODY_GEN = 'body-gen';
    const STATUS_HEADER_GEN = 'header-gen';
    const STATUS_SEO_TITLE_GEN = 'seo-title-gen';
    const STATUS_SEO_DESCRIPTION_GEN = 'seo-description-gen';
    const STATUS_IMAGE_ALT_GEN = 'image-alt-gen';
    const STATUS_BACKLINK_INSERT ='backlink-insert';
    const STATUS_IMAGE_POST = 'img-post';
    const STATUS_TEXT_POST = 'text-post';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';


    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function __construct()
    {
        parent::__construct();
        $this->setRecordCreated(new \DateTime());
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

    public function setBlogId(string $blogId)
    {
        $this->set('blogId', $blogId);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getBlogId() : string
    {
        return $this->get('blogId');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setCampaignId(string $campaignId)
    {
        $this->set('campaignId', $campaignId);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getCampaignId() : string
    {
        return $this->get('campaignId');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setBodyId(string $bodyId)
    {
        $this->set('bodyId', $bodyId);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getBodyId() : string
    {
        return $this->get('bodyId');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setHeaderId(string $headerId)
    {
        $this->set('headerId', $headerId);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getHeaderId() : string
    {
        return $this->get('headerId');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setSeoTitleId(string $seoTitleId)
    {
        $this->set('seoTitleId', $seoTitleId);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getSeoTitleId() : string
    {
        return $this->get('seoTitleId');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setSeoDescriptionId(string $seoDescriptionId)
    {
        $this->set('seoDescriptionId', $seoDescriptionId);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getSeoDescriptionId() : string
    {
        return $this->get('seoDescriptionId');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setImageId(string $setImageId)
    {
        $this->set('setImageId', $setImageId);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getImageId() : string
    {
        return $this->get('setImageId');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setImageAltId(string $imageAltId)
    {
        $this->set('imageAltId', $imageAltId);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getImageAltId() : string
    {
        return $this->get('imageAltId');
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function setRecordCreated(\DateTime $time = null)
    {
        if(!$time){
            $time = new \DateTime();
            $time->format('U');
        }

        $this->set('recordCreated', $time->getTimestamp());

    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    public function getRecordCreated() : \DateTime
    {
        $ret = new \DateTime();
        $unixtimestamp = $this->get('recordCreated');
        $ret->setTimestamp($unixtimestamp);
        return $ret;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
}