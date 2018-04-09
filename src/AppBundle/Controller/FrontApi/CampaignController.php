<?php

namespace AppBundle\Controller\FrontApi;

use Rbl\CouchbaseBundle\Entity\CbCampaign;
use AppBundle\Extension\ApiResponse;
use Rbl\CouchbaseBundle\Model\CampaignModel;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Rbl\CouchbaseBundle\CouchbaseService;
use Symfony\Component\Security\Core\User\UserInterface;


/**
 * @Route(service="app_bundle.campaign.controller")
 */

class CampaignController extends Controller
{

    private $cb;
    private $campaignModel;

    const HTTP_STRING = 'http://';

    public function __construct(CouchbaseService $cb)
    {
        $this->cb = $cb;
        $this->campaignModel = new CampaignModel($this->cb);
    }

    /**
     * @Route("/campaign/upsert", name="frontapi_campaign_upsert")
     * @param Request $request
     * @Method("POST")
     * @return ApiResponse
     */
    public function upsertCampaign(Request $request, UserInterface $user)
    {
        $data = json_decode($request->getContent(), true);
        $this->checkCampaignId($data);

        try {
            if(isset($data['campaignId']) && $data['campaignId']) {
                $object = $this->campaignModel->get($data['campaignId']);
                $object->setPostMainDomainLinks(0);
                $object->setPostSubLinks(0);
            }else{
                $object = new CbCampaign();
                $object->setUserId($user->getId());
                $object->setEnabled(true);
                $object->setType($data['type']);
                $object->setPosted(0);
                $object->setCreated();
                $object->setStatus(CbCampaign::STATUS_READY);
                $object->setNextPostTime(new \DateTime());
            }

            if($data['type'] == CbCampaign::TYPE_BACKLINKED){
                $object->setMainDomain(str_replace(self::HTTP_STRING, '', $data['mainDomain']));
                $object->setAdditionalKeysPercentage($data['additionalKeysPercentage']);
                $object->setPostMainDomainLinks($data['postMainDomainLinks']);
                $object->setPostSubLinks($data['postSubLinks']);
                $object->setMainKeywords($data['mainKeywords']);

                if(isset($data['subLinks'])) foreach($data['subLinks'] as $key => $subLink){
                    $data['subLinks'][$key]['subLink'] = str_replace(self::HTTP_STRING, '', $subLink['subLink']);
                    $data['subLinks'][$key]['subLinkKeywords'] = preg_split("/[," . PHP_EOL . "]+/", $subLink['subLinkKeywords']);
                }
                $object->setSubLinks($data['subLinks']);


                $object->setNeedPosts($data['postSubLinks'] + $data['postMainDomainLinks']);
            }else{
                $object->setPostMainDomainLinks(0);
                $object->setPostSubLinks(0);
                $object->setNeedPosts($data['needPosts']);
            }

            $object->setPostPeriodDays($data['postPeriodDays']);
            $object->setBlogs(array_fill_keys($data['selectedBlogs'], 0));
            $object->setBlogTags(array_map('trim', explode(',', $data['blogTags'])));

            $this->campaignModel->upsert($object);

            return new ApiResponse(true);
        } catch (Exception $e) {
            return ApiResponse::resultError(500, $e->getMessage());
        }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @Route("/campaign/list", name="frontapi_campaign_list")
     * @Method("GET")
     * @return ApiResponse
     */
    public function getCampaignList(UserInterface $user)
    {
        try {
            $status = array(
                CbCampaign::STATUS_READY,
                CbCampaign::STATUS_PROCESSING,
                CbCampaign::STATUS_COMPLETED,
                CbCampaign::STATUS_PAUSED
            );

            $arrayOfObjects = $this->campaignModel->getCampaignsByStatus($status);

            if ($arrayOfObjects != null){

                $ret = [];
                foreach($arrayOfObjects as $object) {
                    if($object->getType() != CbCampaign::TYPE_BACKLINKED){
                        continue;
                    }

                    if(!in_array('ROLE_ADMIN', $user->getRoles()) && $object->getUserId() != $user->getId()){
                        continue;
                    }

                    $campaign = array(
                        'id' => $object->getObjectId(),
                        'enabled' => $object->getEnabled(),
                        'status' => $object->getStatus(),
                        'needPosts' => $object->getNeedPosts(),
                        'postPeriodDays' => $object->getPostPeriodDays(),
                        'nextPostTime' => $object->getNextPostTime()->format('d-m-Y h:i:s'),
                        'posted' => $object->getPosted(),
                        'created' => $object->getCreated()->format('d-m-Y'),
                        'type' => $object->getType(),
                        'blogs' => $object->getBlogs(),
                        'blogTags' => $object->getBlogTags()
                    );

                    if($object->getType() == CbCampaign::TYPE_BACKLINKED){
                        $campaign['mainDomain'] = $object->getMainDomain();
                        $campaign['mainKeywords'] = $object->getMainKeywords();
                        $campaign['subLinks'] = $object->getSubLinks();
                        $campaign['additionalKeysPercentage'] = $object->getAdditionalKeysPercentage();
                        $campaign['postMainDomainLinks'] = $object->getPostMainDomainLinks();
                        $campaign['postSubLinks'] = $object->getPostSubLinks();
                    }

                    $ret[] = $campaign;
                }

                return new ApiResponse($ret);
            } else {
                return ApiResponse::resultNotFound();
            }
        } catch (Exception $e) {
            return ApiResponse::resultError(500, $e->getMessage());
        }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @Route("/campaign/remove", name="frontapi_campaign_remove")
     * @param Request $request
     * @Method("POST")
     * @return ApiResponse
     */
    public function removeCampaign(Request $request, UserInterface $user){

        $data = json_decode($request->getContent(), true);
        $this->checkCampaignId($data);

        try {
            $campaignObject = $this->campaignModel->get($data['campaignId']);
            if ($campaignObject != null){

                if(!in_array('ROLE_ADMIN', $user->getRoles()) && $campaignObject->getUserId() != $user->getId()){
                    return ApiResponse::resultNotFound();
                }

                $campaignObject->setStatus(CbCampaign::STATUS_DELETED);
                $this->campaignModel->upsert($campaignObject);
                return ApiResponse::resultValue(true);
            } else {
                return ApiResponse::resultNotFound();
            }
        } catch (Exception $e) {
            return ApiResponse::resultError(500, $e->getMessage());
        }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @Route("/campaign/enable", name="frontapi_campaign_enable")
     * @param Request $request
     * @Method("GET")
     * @return ApiResponse
     */
    public function enableCampaign(Request $request, UserInterface $user)
    {
        $data = $request->query->all();

        if(!isset($data['enabled'])){
            return ApiResponse::resultNotFound();
        }

        try {
            $campaignObject = $this->campaignModel->get($data['campaignId']);

            if(!in_array('ROLE_ADMIN', $user->getRoles()) && $campaignObject->getUserId() != $user->getId()){
                return ApiResponse::resultNotFound();
            }

            $campaignObject->setEnabled(filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN));
            $this->campaignModel->upsert($campaignObject);

        } catch (Exception $e) {
            return ApiResponse::resultError(500, $e->getMessage());
        }

        return ApiResponse::resultValue(true);

    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @param array $data
     * @Method("GET")
     * @return ApiResponse
     */
    protected function checkCampaignId($data){
        if(!isset($data['campaignId']) || !is_string($data['campaignId']) || strpos($data['campaignId'], 'campaign') === false) {
            return ApiResponse::resultNotFound();
        }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

}
