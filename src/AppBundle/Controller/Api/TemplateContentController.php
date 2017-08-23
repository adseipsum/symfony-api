<?php

namespace AppBundle\Controller\Api;

use AppBundle\Entity\CbTemplate;
use AppBundle\Extension\ApiResponse;
use AppBundle\Extension\EditorExtension;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Repository\TemplateModel;

class TemplateContentController extends Controller
{
    /**
     * @Route("/template/list", name="api_template_list")
     * @Method("GET")
     */
    public function getTemplateList()
    {
        $username = $this->getUser()->getUsernameCanonical();
        if ($username == null) {
            return ApiResponse::resultUnauthorized();
        }

        try {
            $cb = $this->get('couchbase.connector');
            $model = new TemplateModel($cb);
            $model->warmup();
            $objects = $model->getAllObjects();

            $ret = [];

            foreach ($objects as $object) {
                $elem = [];
                $elem['id'] = $object->getObjectId();
                $elem['name'] = $object->getName();
                $ret [] = $elem;
            }

            return ApiResponse::resultValues($ret);
        } catch (Exception $e) {
            return ApiResponse::resultError(500, $e->getMessage());
        }
    }

    /**
     * @Route("/template/content/{templateId}", name="api_template_get", requirements={"template": "[a-zA-Z0-9\-\:]+"})
     * @Method("GET")
     */
    public function getTemplateContent($templateId)
    {
        $username = $this->getUser()->getUsernameCanonical();
        if ($username == null) {
            return ApiResponse::resultUnauthorized();
        }

        try {
            $cb = $this->get('couchbase.connector');
            $model = new TemplateModel($cb);
            $object = $model->get($templateId);

            if($object != null)
            {
                $ret = [];
                $ret['id'] = $object->getObjectId();
                $ret['name'] = $object->getName();
                $ret['template'] = $object->getTemplate();
                $ret['count'] = $object->getCount();

                return ApiResponse::resultValue($ret);
            }
            else {
                return ApiResponse::resultNotFound();
            }

        } catch (Exception $e) {
            return ApiResponse::resultError(500, $e->getMessage());
        }
    }

    /**
     * @Route("/template/content/{templateId}", name="api_template_update", requirements={"template": "[a-zA-Z0-9\-\:]+"})
     * @Method("POST")
     */
    public function updateTemplateContent(Request $request, $templateId)
    {
        $username = $this->getUser()->getUsernameCanonical();
        if ($username == null) {
            return ApiResponse::resultUnauthorized();
        }

        try {
            $cb = $this->get('couchbase.connector');
            $model = new TemplateModel($cb);
            $data = json_decode($request->getContent(), true);


            /*
             * {
             *    "id":  "id"
             *    "name" "name"
             *    "template" "template"
             * }
             */

            if($templateId == 'new')
            {
                $object = new CbTemplate();
                $object->setName($data['name']);
                $object->setTemplate($data['template']);
                $model->upsert($object);

            }
            else {
                $object = $model->get($templateId);

                if($object != null)
                {
                    $object->setName($data['name']);
                    $object->setTemplate($data['template']);
                    $model->upsert($object);

                    $ret = [];
                    $ret['id'] = $object->getObjectId();
                    $ret['name'] = $object->getName();
                    $ret['template'] = $object->getTemplate();
                    $ret['count'] = $object->getCount();

                    return ApiResponse::resultValue($ret);
                }
                else {
                    return ApiResponse::resultNotFound();
                }
            }
        } catch (Exception $e) {
            return ApiResponse::resultError(500, $e->getMessage());
        }
    }


    /**
     * @Route("/template/usage/plus", name="api_template_usage_plus", requirements={"template": "[a-zA-Z0-9\-\:]+"})
     * @Method("POST")
     */
    public function usagePlusCount(Request $request, $templateId)
    {

    }

}
