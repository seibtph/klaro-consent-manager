<?php

declare(strict_types=1);

/*
 * Klaro Consent Manager bundle for Contao Open Source CMS
 *
 * Copyright (c) 2022 pdir / digital agentur // pdir GmbH
 *
 * @package    klaro-consent-manager
 * @link       https://pdir.de/consent/
 * @license    LGPL-3.0-or-later
 * @author     Mathias Arzberger <develop@pdir.de>
 * @author     Christian Mette <develop@pdir.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pdir\ContaoKlaroConsentManager\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Message;
use Contao\StringUtil;
use Pdir\ContaoKlaroConsentManager\Model\KlaroPurposeModel;
use Pdir\ContaoKlaroConsentManager\Model\KlaroServiceModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class KlaroTranslationListener
{
    /**
     * @Callback(
     *     table="tl_klaro_translation",
     *     target="fields.purposes.load"
     * )
     */
    public function purposesLoad($value, DataContainer $dc)
    {
        $flattenStoredPurposes = $this->flatten(StringUtil::deserialize($value ?? []));
        $availablePurposes = KlaroPurposeModel::findAll();
        $buffer = [];

        if (null !== $availablePurposes)
        {
            foreach ($availablePurposes as $ap)
            {
                // if the stored value is empty, the value defined in the table is used instead
                $buffer[] = ['key' => $ap->klaro_key, 'value' => empty($flattenStoredPurposes[$ap->klaro_key]) ? $ap->title : $flattenStoredPurposes[$ap->klaro_key]];
            }
            $value = serialize($buffer);
        } else {
            // Klaro is not yet configured correctly
            $GLOBALS['TL_DCA']['tl_klaro_translation']['fields']['purposes']['eval']['disabled'] = true;
            Message::addError($GLOBALS['TL_LANG']['tl_klaro_translation']['purposes_empty']);

        }
        return $value;
    }

    /**
     * @Callback(
     *     table="tl_klaro_translation",
     *     target="fields.purposes.save"
     * )
     * checks if all available purpose keys are specified,
     * invalid keys are not stored, if an invalid key is present
     * an exception is thrown
     */
    public function purposesSave($value, DataContainer $dc)
    {
        // tolerant treatment of missing parameters
        if (null === $purposeModel = KlaroPurposeModel::findAll()) {
            return $value;
        }

        $availablePurposes = $purposeModel->fetchEach('klaro_key');
        $savedPurposesValues = array_map(static fn ($value) => $value['key'], StringUtil::deserialize($value ?? []));
        $arrDifferences = array_diff($savedPurposesValues, $availablePurposes);

        if (0 !== \count($arrDifferences)) {
            $strMsg = $GLOBALS['TL_LANG']['tl_klaro_translation']['purposesSaveError'];
            $arrPns = $GLOBALS['TL_LANG']['tl_klaro_translation']['purposesSavePronouns'];
            $strDifferences = implode(', ', $arrDifferences);
            $strPurposes = implode(', ', $availablePurposes);
            [$a,$b] = 1 === \count($arrDifferences) ? $arrPns['sgl'] : $arrPns['pl'];

            Message::addError(sprintf($strMsg, $a, $strDifferences, $b, $strPurposes));

            throw new \Exception();
        }

        return $value;
    }

    /**
     * @Callback(
     *     table="tl_klaro_translation",
     *     target="fields.services.load"
     * )
     */
    public function servicesLoad($value, DataContainer $dc)
    {
        $flattenStoredServices = $this->flatten(StringUtil::deserialize($value ?? []));
        $availableServices = KlaroServiceModel::findAll();
        $buffer = [];

        if (null !== $availableServices)
        {
            foreach ($availableServices as $as)
            {
                // if the stored value is empty, the value defined in the table is used instead
                $buffer[] = ['key' => $as->name, 'value' => empty($flattenStoredServices[$as->name]) ? $as->title : $flattenStoredServices[$as->name]];
            }
            $value = serialize($buffer);
        } else {
            // Klaro is not yet configured correctly
            $GLOBALS['TL_DCA']['tl_klaro_translation']['fields']['services']['eval']['disabled'] = true;
            Message::addError($GLOBALS['TL_LANG']['tl_klaro_translation']['services_empty']);

        }
        return $value;
    }

    /**
     * @Callback(
     *     table="tl_klaro_translation",
     *     target="fields.services.save"
     * )
     * checks whether all available services keys are specified,
     * invalid keys are not stored, if an invalid key is present
     * an exception is thrown
     */
    public function servicesSave($value, DataContainer $dc)
    {
        // tolerant treatment of missing parameters
        if (null === $serviceModel = KlaroServiceModel::findAll()) {
            return $value;
        }

        $availableServices = $serviceModel->fetchEach('name');
        $savedServicesValues = array_map(static fn ($value) => $value['key'], StringUtil::deserialize($value ?? []));
        $arrDifferences = array_diff($savedServicesValues, $availableServices);

        if (0 !== \count($arrDifferences)) {
            $strMsg = $GLOBALS['TL_LANG']['tl_klaro_translation']['servicesSaveError'];
            $arrPns = $GLOBALS['TL_LANG']['tl_klaro_translation']['servicesSavePronouns'];
            $strDifferences = implode(', ', $arrDifferences);
            $strServices = implode(', ', $availableServices);
            [$a,$b] = 1 === \count($arrDifferences) ? $arrPns['sgl'] : $arrPns['pl'];

            Message::addError(sprintf($strMsg, $a, $strDifferences, $b, $strServices));

            throw new \Exception();
        }

        return $value;
    }

    /**
     * /**
     * @Callback(
     *     table="tl_klaro_translation",
     *     target="fields.ccMonitor.input_field"
     * )
     */
    public function ccMonitorInputField(DataContainer $dc)
    {
        [$label, $tip] = $GLOBALS['TL_LANG']['tl_klaro_translation']['ccMonitor'];

        return <<< HTML
            <div class="clr widget">
                <h3>
                    <label for="ctrl_ccAcceptAlways">$label</label>
                </h3>
                <div data-type="placeholder">
                    <div class="klaro cm-as-context-notice" lang="de" >
                        <div class="context-notice" >
                            <p id="ccmQuestion">Translation?</p>
                            <p class="cm-buttons">
                                <button id="ccmButtonOnce" class="cm-btn cm-btn-success" type="button">Ja</button>
                                <button id="ccmButtonAlways" class="cm-btn cm-btn-success-var" type="button">Immer</button>
                            </p>
                        </div>
                    </div>
                </div>
                <p class="tl_help tl_tip" title="">$tip</p>
            </div>
            HTML;
    }

    /**
     * transforms the result of the key-value-wizzard from
     *
     *      [
     *          0 => [key1 => value],
     *          1 => [key2 => value],
     *          n => [key3 => value],
     *      ]
     * to
     *      [key1 => value, key2 => value, keyn => value]
     *
     * the keys must be unique!
     *
     * @param $arr
     * @return array
     */
    private function flatten($arr):array
    {
        $buffer = [];

        foreach($arr as $i => $data) { $buffer[$data['key']] = $data['value']; }

        return $buffer;
    }
}