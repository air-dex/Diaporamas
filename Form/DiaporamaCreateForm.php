<?php
/*************************************************************************************/
/*      This file is part of the "Diaporamas" Thelia 2 module.                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Diaporamas\Form;

use Diaporamas\Diaporamas;
use Diaporamas\Model\Diaporama;
use Diaporamas\Model\DiaporamaTypeQuery;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\ExecutionContextInterface;
use Thelia\Form\BaseForm;

/**
 * Class DiaporamaCreateForm
 * @package Diaporamas\Form
 */
class DiaporamaCreateForm extends BaseForm
{
    const FORM_NAME = 'diaporama_create';

    public function buildForm()
    {
        $translationKeys = $this->getTranslationKeys();
        $fieldsIdKeys = $this->getFieldsIdKeys();

        $this->addTitleField($translationKeys, $fieldsIdKeys);
        $this->addShortcodeField($translationKeys, $fieldsIdKeys);
        $this->addLocaleField();
    }

    public function addLocaleField()
    {
        $this->formBuilder->add(
            'locale',
            'hidden',
            [
                'constraints' => [ new NotBlank() ],
                'required'    => true,
            ]
        );
    }

    protected function addTitleField(array $translationKeys, array $fieldsIdKeys)
    {
        $this->formBuilder->add("title", "text", array(
            "label" => $this->trans($this->readKey('title', $translationKeys)),
            "label_attr" => ["for" => $this->readKey("title", $fieldsIdKeys)],
            "required" => true,
            "constraints" => array(
                new NotBlank(),
            ),
            "attr" => array(
                'placeholder' => $this->trans('diaporama.create.title.placeholder'),
            )
        ));
    }

    protected function addShortcodeField(array $translationKeys, array $fieldsIdKeys)
    {
        $this->formBuilder->add("shortcode", "text", array(
            "label" => $this->trans($this->readKey("shortcode", $translationKeys)),
            "label_attr" => ["for" => $this->readKey("shortcode", $fieldsIdKeys)],
            "required" => true,
            "constraints" => array(
                new NotBlank(),
                new Regex(array(
                    'pattern' => Diaporama::SHORTCODE_REGEX,
                    'match' => true,
                    'message' => $this->trans('diaporama.create.shortcode.regex_fail'),
                )),
            ),
            "attr" => array(
                'placeholder' => $this->trans('diaporama.create.shortcode.placeholder'),
            )
        ));
    }

    public function getName()
    {
        return static::FORM_NAME;
    }

    public function readKey($key, array $keys, $default = '')
    {
        return isset($keys[$key]) ? $keys[$key] : $default;
    }

    public function getFieldsIdKeys()
    {
        return array(
            'title' => "diaporama_title",
            'shortcode' => "diaporama_shortcode",
        );
    }

    public function getTranslationKeys()
    {
        return array(
            'title' => 'diaporama.create.title',
            'shortcode' => 'diaporama.create.shortcode',
        );
    }

    public function trans($code, array $parameters = array(), $domain = Diaporamas::BO_MESSAGE_DOMAIN)
    {
        return $this->translator->trans($code, $parameters, $domain);
    }
}
