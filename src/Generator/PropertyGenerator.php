<?php namespace PHPFHIR\Generator;

use PHPFHIR\Enum\ElementTypeEnum;
use PHPFHIR\Enum\PHPScopeEnum;
use PHPFHIR\Enum\SimplePropertyTypesEnum;
use PHPFHIR\Template\ClassTemplate;
use PHPFHIR\Template\PropertyTemplate;
use PHPFHIR\Utilities\NameUtils;
use PHPFHIR\Utilities\SimpleTypeUtils;
use PHPFHIR\Utilities\XMLUtils;
use PHPFHIR\XSDMap;

/**
 * Class PropertyGenerator
 * @package PHPFHIR\Utilities
 */
abstract class PropertyGenerator
{
    /**
     * TODO: I don't like how this is utilized, really.  Should think of a better way to do it.
     *
     * @param XSDMap $XSDMap
     * @param \SimpleXMLElement $propertyElement
     * @param ClassTemplate $classTemplate
     */
    public static function implementProperty(XSDMap $XSDMap, \SimpleXMLElement $propertyElement, ClassTemplate $classTemplate)
    {
        switch(strtolower($propertyElement->getName()))
        {
            case ElementTypeEnum::ATTRIBUTE:
                PropertyGenerator::implementAttributeProperty($XSDMap, $propertyElement, $classTemplate);
                break;
            case ElementTypeEnum::CHOICE:
                PropertyGenerator::implementChoiceProperty($XSDMap, $propertyElement, $classTemplate);
                break;
            case ElementTypeEnum::SEQUENCE:
                PropertyGenerator::implementSequenceProperty($XSDMap, $propertyElement, $classTemplate);
                break;
            case ElementTypeEnum::UNION:
                PropertyGenerator::implementUnionProperty($XSDMap, $propertyElement, $classTemplate);
                break;
        }
    }

    /**
     * @param XSDMap $XSDMap
     * @param string $name
     * @param string $type
     * @param string|null $maxOccurs
     * @param string|null|array $documentation
     * @param ClassTemplate $classTemplate
     * @return PropertyTemplate
     */
    public static function buildProperty(XSDMap $XSDMap, $name, $type, $maxOccurs, $documentation, ClassTemplate $classTemplate)
    {
        if (preg_match('{^[A-Z]}S', $type))
            return self::buildComplexProperty($XSDMap, $name, $type, $maxOccurs, $documentation, $classTemplate);

        return self::buildSimpleProperty($name, $type, $maxOccurs, $documentation);
    }

    /**
     * @param string $name
     * @param string $type
     * @param string|null $maxOccurs
     * @param string|array|null $documentation
     * @return PropertyTemplate
     */
    public static function buildSimpleProperty($name, $type, $maxOccurs, $documentation)
    {
        if (false !== ($pos = strpos($type, '-primitive')))
            $type = substr($type, 0, $pos);

        $propertyTemplate = self::newPropertyTemplate($name, $maxOccurs, $documentation);
        $propertyTemplate->addType(
            SimpleTypeUtils::getSimpleTypeVariableType(
                new SimplePropertyTypesEnum(strtolower($type))
            )
        );
        return $propertyTemplate;
    }

    /**
     * @param XSDMap $XSDMap
     * @param string $name
     * @param string $type
     * @param string|null $maxOccurs
     * @param string|array|null $documentation
     * @param ClassTemplate $classTemplate
     * @return PropertyTemplate
     */
    public static function buildComplexProperty(XSDMap $XSDMap, $name, $type, $maxOccurs, $documentation, ClassTemplate $classTemplate)
    {
        $propertyTemplate = self::newPropertyTemplate($name, $maxOccurs, $documentation);
        $propertyTemplate->addType($XSDMap->getClassNameForObject($type));

        $classTemplate->addUse($XSDMap->getClassUseStatementForObject($type));

        return $propertyTemplate;
    }

    /**
     * @param string $name
     * @param string|number $maxOccurs
     * @param array|string|null $documentation
     * @return PropertyTemplate
     */
    public static function newPropertyTemplate($name, $maxOccurs, $documentation)
    {
        $propertyTemplate = new PropertyTemplate(
            $name,
            new PHPScopeEnum(PHPScopeEnum::_PRIVATE),
            self::determineIfCollection($maxOccurs));

        $propertyTemplate->setDocumentation($documentation);

        return $propertyTemplate;
    }

    /**
     * @param string|number $maxOccurs
     * @return bool
     */
    public static function determineIfCollection($maxOccurs)
    {
        return 'unbounded' === strtolower($maxOccurs) || (is_numeric($maxOccurs) && (int)$maxOccurs > 1);
    }

    /**
     * @param XSDMap $XSDMap
     * @param \SimpleXMLElement $sequence
     * @param ClassTemplate $classTemplate
     */
    public static function implementSequenceProperty(XSDMap $XSDMap, \SimpleXMLElement $sequence, ClassTemplate $classTemplate)
    {
        // Check if this is a simple or complex sequence
        $elements = $sequence->xpath('xs:element');
        if (0 === count($elements))
        {
            foreach($sequence->children('xs', true) as $_element)
            {
                /** @var \SimpleXMLElement $_element */
                switch(strtolower($_element->getName()))
                {
                    case ElementTypeEnum::CHOICE:
                        self::implementChoiceProperty($XSDMap, $_element, $classTemplate);
                        break;
                }
            }
        }
        else
        {
            foreach($elements as $element)
            {
                $attributes = $element->attributes();
                $name = (string)$attributes['name'];

                // TODO: Handle these situations
                if ('' === $name)
                {
                    $ref = (string)$attributes['ref'];
                    trigger_error(sprintf('Encountered property with no name and with ref value "%s" on class "%s". Will not create property for it.', $ref, $classTemplate->getClassName()));

                    continue;
                }

                $type = (string)$attributes['type'];
                $maxOccurs = (string)$attributes['maxOccurs'];

                $propertyTemplate = self::buildProperty(
                    $XSDMap,
                    $name,
                    $type,
                    $maxOccurs,
                    XMLUtils::getDocumentation($element),
                    $classTemplate);

                $classTemplate->addProperty($propertyTemplate);

                MethodGenerator::implementMethodsForProperty($classTemplate, $propertyTemplate);
            }
        }
    }

    /**
     * @param XSDMap $XSDMap
     * @param \SimpleXMLElement $choice
     * @param ClassTemplate $classTemplate
     */
    public static function implementChoiceProperty(XSDMap $XSDMap, \SimpleXMLElement $choice, ClassTemplate $classTemplate)
    {
        $attributes = $choice->attributes();
//        $minOccurs = (int)$attributes['minOccurs'];
        $maxOccurs = $attributes['maxOccurs'];
        $documentation = XMLUtils::getDocumentation($choice);

        foreach($choice->xpath('xs:element') as $_element)
        {
            $attributes = $_element->attributes();
            $name = (string)$attributes['name'];
            $ref = (string)$attributes['ref'];
            $type = (string)$attributes['type'];

            if ('' === $name && '' === $ref)
                throw new \RuntimeException('Unable to determine name of choice property in class '.$classTemplate->getClassName().'.');

            if ('' === $ref)
            {
                $propTemplate = self::buildProperty($XSDMap, $name, $type, $maxOccurs, $documentation, $classTemplate);
            }
            else if ('' === $name)
            {
                $type = NameUtils::getComplexTypeClassName($ref);
                $propTemplate = self::buildProperty($XSDMap, $type, $type, $maxOccurs, $documentation, $classTemplate);
            }
            else
            {
                trigger_error('Unable to parse choice property with definition '.(string)$_element);
                continue;
            }

            $classTemplate->addProperty($propTemplate);

            MethodGenerator::implementMethodsForProperty($classTemplate, $propTemplate);
        }
    }

    /**
     * @param XSDMap $XSDMap
     * @param \SimpleXMLElement $attribute
     * @param ClassTemplate $classTemplate
     */
    public static function implementAttributeProperty(XSDMap $XSDMap, \SimpleXMLElement $attribute, ClassTemplate $classTemplate)
    {
        $attributes = $attribute->attributes();
        $name = (string)$attributes['name'];
        $type = (string)$attributes['type'];

        $propertyTemplate = self::buildProperty($XSDMap, $name, $type, 1, XMLUtils::getDocumentation($attribute), $classTemplate);

        $classTemplate->addProperty($propertyTemplate);

        MethodGenerator::implementMethodsForProperty($classTemplate, $propertyTemplate);
    }

    public static function implementUnionProperty(XSDMap $XSDMap, \SimpleXMLElement $union, ClassTemplate $classTemplate)
    {
        // TODO: Implement these!
    }
}