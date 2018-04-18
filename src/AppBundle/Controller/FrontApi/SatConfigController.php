<?php

namespace AppBundle\Controller\FrontApi;

use Rbl\CouchbaseBundle\Entity\CbSatConfig;
use Rbl\CouchbaseBundle\Model\SatConfigModel;
use AppBundle\Extension\ApiResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Rbl\CouchbaseBundle\CouchbaseService;


class SatConfigController extends Controller
{

    private $cb;
    private $satConfigModel;

    public function __construct(CouchbaseService $cb)
    {
        $this->cb = $cb;
        $this->satConfigModel = new SatConfigModel($this->cb);
    }

    /**
     * @Route("/config/save", name="frontapi_config_save")
     * @param Request $request
     * @Method("POST")
     * @return ApiResponse
     */
    public function saveSatConfig(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $additionalKeywords = isset($data['additionalKeywords']) && $data['additionalKeywords'] ? array_map('trim', explode(',', $data['additionalKeywords'])) : array();
        try {
            $satConfigObject = $this->satConfigModel->get(CbSatConfig::SAT_CONFIG);
            $satConfigObject->setAdditionalKeywords($additionalKeywords);
            $this->satConfigModel->upsert($satConfigObject);

            return new ApiResponse(true);
        } catch (Exception $e) {
            return ApiResponse::resultError(500, $e->getMessage());
        }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * @Route("/config/show", name="frontapi_config_show")
     * @Method("GET")
     * @return ApiResponse
     */
    public function showSatConfig()
    {
        try {
            $satConfigObject = $this->satConfigModel->get(self::SAT_CONFIG);
            if (!$satConfigObject) {
                return ApiResponse::resultNotFound();
            }

            $config = array(
                'additionalKeywords' => $satConfigObject->getAdditionalKeywords(),
            );

            return new ApiResponse($config);
        } catch (Exception $e) {
            return ApiResponse::resultError(500, $e->getMessage());
        }
    }/////////////////////////////////////////////////////////////////////////////////

}
