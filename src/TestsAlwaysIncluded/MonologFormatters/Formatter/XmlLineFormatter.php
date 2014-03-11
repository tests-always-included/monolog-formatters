<?php

namespace TestsAlwaysIncluded\MonologFormatters\Formatter;

use Monolog\Formatter\LineFormatter;
use \DOMDocument;
use \DOMXPath;

/**
 * Adds formatting and sanitizing of XML
 * from the context array
 */
class XmlLineFormatter extends LineFormatter
{

    const SANITIZE_CHARACTER = '*';

    protected $domDocument;
    protected $xPathNamespacs = array();
    protected $xPathRules = array();

    /**
     * {@inheritDoc}
     */
    public function __construct($format = null, $dateFormat = null)
    {
        parent::__construct($format, $dateFormat);
        $this->initializeDomDocument();
    }

    protected function convertToString($data)
    {
        $domDocument = $this->getDomDocument();
        $result = @$domDocument->loadXML($data);
        if(true === $result)
        {
            if(0 < count($this->getXPathRules()))
            {
                $domDocument = $this->sanitizeXML($domDocument);
            }
            $formattedXML = @$domDocument->saveXML() . "\n";
            return $formattedXML;
        }
        else
        {
            return parent::convertToString($data);
        }
    }

    protected function createAndReturnXPath(DOMDocument $domDocument)
    {
        $xPath = new DOMXPath($domDocument);
        foreach($this->getXPathNamespaces() as $ns => $url)
        {
            $xPath->registerNamespace($ns, $url);
        }
        return $xPath;
    }

    /**
     * {@inheritDoc}
     */
    public function format(array $record)
    {
        $context = $record['context'];
        unset($record['context']);

        $output = parent::format($record);
        foreach($context as $var => $val)
        {
            if(false !== strpos($output, '%context.' . $var . '%'))
            {
                $output = str_replace('%context.' . $var . '%', $this->convertToString($val), $output);
                unset($context[$var]);
            } 
            else
            {
                $context[$var] = $this->convertToString($val);
            }
        }
        $output = str_replace('%context%', $this->convertToString($context), $output);
        return $output;
    }

    protected function initializeDomDocument()
    {
        $domDocument = new DOMDocument();
        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = true;
        $this->setDomDocument($domDocument);
    }

    protected function sanitizeValue($value)
    {
        return preg_replace(array('/\d/', '/\w/'), self::SANITIZE_CHARACTER, $value);
    }

    protected function sanitizeXML(DOMDocument $domDocument)
    {
        $xPath = $this->createAndReturnXPath($domDocument);
        foreach($this->getXPathRules() as $rule)
        {
            $resultElements = $xPath->query($rule);
            foreach($resultElements as $element)
            {
                $sanitizedValue = $this->sanitizeValue($element->nodeValue);
                $element->nodeValue = $sanitizedValue;
            }
        }
        return $domDocument;
    }

    public function setDomDocument(DOMDocument $domDocument)
    {
        $this->domDocument = $domDocument;
    }

    public function getDomDocument()
    {
        return $this->domDocument;
    }

    public function setXPathNamespaces(array $namespaces)
    {
        foreach($namespaces as $ns => $url)
        {
            if(false === is_string($ns) || false === is_string($url))
            {
                throw new \UnexpectedValueException('$namespaces must contain an array of namespace keys and url values');
            }
        }
        $this->xPathNamespaces = $namespaces;
    }

    public function getXPathNamespaces()
    {
        return $this->xPathNamespaces;
    }

    public function setXPathRules(array $rules)
    {
        foreach($rules as $rule)
        {
            if(false === is_string($rule))
            {
                throw new \UnexpectdValueException('$rules must contain an array of strings');
            }
        }
        $this->xPathRules = $rules;
    }

    public function getXPathRules()
    {
        return $this->xPathRules;
    }
}
