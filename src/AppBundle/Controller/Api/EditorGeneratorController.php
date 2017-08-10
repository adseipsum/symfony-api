<?php

namespace AppBundle\Controller\Api;

use AppBundle\Extension\ApiResponse;
use AppBundle\Extension\EditorExtension;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class EditorGeneratorController extends Controller
{

    /**
     * @Route("/editor/generate/{template}", name="api_editor_generate_template", requirements={"template": "[a-zA-Z0-9\-\-]+"})
     * @Method("POST")
     */
    public function generateTemplate(Request $request, $template)
    {
        $username = $this->getUser()->getUsernameCanonical();
        if($username == null)
        {
            return ApiResponse::resultUnauthorized();
        }
        $extEditor = new EditorExtension($this->getParameter('generator_user_dir'), $username, $template);

        $data = json_decode($request->getContent(), true);
        /*
         * {
         *    "meta": {}
         *    "data": {spinblocks}
         * }
         */

        $extEditor->setSpinblockData($data['data']);
        return ApiResponse::resultOk();
    }

    /**
     * @Route("/editor/generateblock/{template}", name="api_editor_generate_block", requirements={"template": "[a-zA-Z0-9\-\-]+"})
     * @Method("POST")
     */
    public function generateBlock(Request $request, $template)
    {
        $username = $this->getUser()->getUsernameCanonical();
        if($username == null)
        {
            return ApiResponse::resultUnauthorized();
        }
        $extEditor = new EditorExtension($this->getParameter('generator_user_dir'), $username, $template);

        $data = json_decode($request->getContent(), true);
        /*
         * {
         *    "meta": {}
         *    "data": {spinblocks}
         * }
         */

        $filename = 'temp.tpl';
        $extEditor->genTemplateFileStub($filename);
        $content = $extEditor->genTemplateForBlock($data['data']);

        $result = self::_generateForTemplate($extEditor, $filename, $content);


        return ApiResponse::resultValue($result);
    }


    function _generateForTemplate($ext,  $templateFile, $content)
    {
        $templateName = $ext->getTemplateName();

        $pPython = $this->getParameter('python_bin');
        $pScript = $this->getParameter('generator_home');

        $userDir = $this->getParameter('generator_user_dir');
        $baseTemplate = $this->getParameter('generator_quickcheck_base');

        $username = $this->getUser()->getUsernameCanonical();

        $tmpDir = "$userDir/$username/tmp";
        $templateDir = "$userDir/$username/template";
        $templateFile = "$userDir/$username/template/default/".$templateFile;

        $base_template_content = file_get_contents($templateFile);
        file_put_contents($templateFile, $base_template_content.PHP_EOL.$content);

        $command_validate = "cd $pScript && $pPython $pScript/render.py -DW $tmpDir -DT $templateDir -v -t $templateName -f $templateFile";
        exec($command_validate, $output_validate);

        $out_validate_text = '';
        $validate_ok = true;

        $template_text_lines = preg_split("/\\n/", $content);
        $template_lines = [];
        $first_line = $ext->getLineCount($base_template_content);
        $count = 0;

        foreach ($template_text_lines as $line)
        {
            $elem['linenum'] = $first_line+$count;
            $elem['text'] = $line;
            $elem['is_valid'] = true;
            $template_lines[] = $elem;
            $count++;
        }

        foreach($output_validate as $line) {
            if(strpos($line, 'TemplateRenderException:') === false)
            {
                // do nothing, wierd logic when match at 0 position != false is true, but === false is false
            }
            else {
                $validate_ok = false;
                preg_match_all('/\(([0-9\:\~\?]+?)\)/', $line, $errors);

                foreach ($errors as $error)
                {
                    $pos = preg_split("/\:/",$error[0]);
                    $linenum = intval(str_replace('(','',$pos[0]));

                    $linenum--;

                    foreach ($template_lines as &$tline)
                    {
                        if($tline['linenum']== $linenum)
                        {
                            $tline['is_valid'] = false;
                        }
                    }
                }
                $out_validate_text = $line."\n";
            }
        }

        $out_finished = '';

        if($validate_ok == true)
        {
            $out_finished = '';
            $command = "cd $pScript && $pPython $pScript/render.py -DW $tmpDir -DT $templateDir -t $templateName -f $templateFile";

            exec($command, $output);

            $brCount = 0;
            foreach($output as $line) {
                $out_finished .= $line . "\n";
                $brCount++;
            }
        }
        else {
            $out_finished = 'ERROR';
        }

        $params = [];
        $params['generated'] = $out_finished;
        $params['validation_lines'] = $template_lines;
        $params['validation_text'] = $out_validate_text;
        $params['validation_status'] = $validate_ok;
        return $params;
    }


}
