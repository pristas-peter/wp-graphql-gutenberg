<?php

declare(strict_types=1);

namespace voku\helper;

/**
 * @property-read string $plaintext
 *                                 <p>Get dom node's plain text.</p>
 *
 * @method static XmlDomParser file_get_xml($xml, $libXMLExtraOptions = null)
 *                                 <p>Load XML from file.</p>
 * @method static XmlDomParser str_get_xml($xml, $libXMLExtraOptions = null)
 *                                 <p>Load XML from string.</p>
 */
class XmlDomParser extends AbstractDomParser
{
    /**
     * @var callable|null
     *
     * @phpstan-var null|callable(string $cssSelectorString, string $xPathString, \DOMXPath, \voku\helper\XmlDomParser): string
     */
    private $callbackXPathBeforeQuery;

    /**
     * @var callable|null
     *
     * @phpstan-var null|callable(string $xmlString, \voku\helper\XmlDomParser): string
     */
    private $callbackBeforeCreateDom;

    /**
     * @var bool
     */
    private $autoRemoveXPathNamespaces = false;

    /**
     * @var bool
     */
    private $autoRegisterXPathNamespaces = false;

    /**
     * @var bool
     */
    private $reportXmlErrorsAsException = false;

    /**
     * @var string[]
     *
     * @phpstan-var array<string, string>
     */
    private $xPathNamespaces = [];

    /**
     * @param \DOMNode|SimpleXmlDomInterface|string $element HTML code or SimpleXmlDomInterface, \DOMNode
     */
    public function __construct($element = null)
    {
        $this->document = new \DOMDocument('1.0', $this->getEncoding());

        // DOMDocument settings
        $this->document->preserveWhiteSpace = true;
        $this->document->formatOutput = true;

        if ($element instanceof SimpleXmlDomInterface) {
            $element = $element->getNode();
        }

        if ($element instanceof \DOMNode) {
            $domNode = $this->document->importNode($element, true);

            if ($domNode instanceof \DOMNode) {
                /** @noinspection UnusedFunctionResultInspection */
                $this->document->appendChild($domNode);
            }

            return;
        }

        if ($element !== null) {
            $this->loadXml($element);
        }
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @throws \BadMethodCallException
     * @throws \RuntimeException
     *
     * @return static
     */
    public static function __callStatic($name, $arguments)
    {
        $arguments0 = $arguments[0] ?? '';

        $arguments1 = $arguments[1] ?? null;

        if ($name === 'str_get_xml') {
            $parser = new static();

            return $parser->loadXml($arguments0, $arguments1);
        }

        if ($name === 'file_get_xml') {
            $parser = new static();

            return $parser->loadXmlFile($arguments0, $arguments1);
        }

        throw new \BadMethodCallException('Method does not exist');
    }

    /** @noinspection MagicMethodsValidityInspection */

    /**
     * @param string $name
     *
     * @return string|null
     */
    public function __get($name)
    {
        $name = \strtolower($name);

        if ($name === 'plaintext') {
            return $this->text();
        }

        return null;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->xml(false, false, true, 0);
    }

    /**
     * Create DOMDocument from XML.
     *
     * @param string   $xml
     * @param int|null $libXMLExtraOptions
     *
     * @return \DOMDocument
     */
    protected function createDOMDocument(string $xml, $libXMLExtraOptions = null): \DOMDocument
    {
        if ($this->callbackBeforeCreateDom) {
            $xml = \call_user_func($this->callbackBeforeCreateDom, $xml, $this);
        }

        // set error level
        $internalErrors = \libxml_use_internal_errors(true);
        if (\PHP_VERSION_ID < 80000) {
            $disableEntityLoader = \libxml_disable_entity_loader(true);
        }
        \libxml_clear_errors();

        $optionsXml = \LIBXML_DTDLOAD | \LIBXML_DTDATTR | \LIBXML_NONET;

        if (\defined('LIBXML_BIGLINES')) {
            $optionsXml |= \LIBXML_BIGLINES;
        }

        if (\defined('LIBXML_COMPACT')) {
            $optionsXml |= \LIBXML_COMPACT;
        }

        if ($libXMLExtraOptions !== null) {
            $optionsXml |= $libXMLExtraOptions;
        }

        $this->xPathNamespaces = []; // reset
        $matches = [];
        \preg_match_all('#xmlns:(?<namespaceKey>.*)=(["\'])(?<namespaceValue>.*)\\2#Ui', $xml, $matches);
        foreach ($matches['namespaceKey'] ?? [] as $index => $key) {
            if ($key) {
                $this->xPathNamespaces[\trim($key, ':')] = $matches['namespaceValue'][$index];
            }
        }

        if ($this->autoRemoveXPathNamespaces) {
            $xml = $this->removeXPathNamespaces($xml);
        }

        $xml = self::replaceToPreserveHtmlEntities($xml);

        $documentFound = false;
        $sxe = \simplexml_load_string($xml, \SimpleXMLElement::class, $optionsXml);
        $xmlErrors = \libxml_get_errors();
        if ($sxe !== false && \count($xmlErrors) === 0) {
            $domElementTmp = \dom_import_simplexml($sxe);
            if (
                $domElementTmp
                &&
                $domElementTmp->ownerDocument instanceof \DOMDocument
            ) {
                $documentFound = true;
                $this->document = $domElementTmp->ownerDocument;
            }
        }

        if ($documentFound === false) {

            // UTF-8 hack: http://php.net/manual/en/domdocument.loadhtml.php#95251
            $xmlHackUsed = false;
            /** @noinspection StringFragmentMisplacedInspection */
            if (\stripos('<?xml', $xml) !== 0) {
                $xmlHackUsed = true;
                $xml = '<?xml encoding="' . $this->getEncoding() . '" ?>' . $xml;
            }

            $documentFound = $this->document->loadXML($xml, $optionsXml);

            // remove the "xml-encoding" hack
            if ($xmlHackUsed) {
                foreach ($this->document->childNodes as $child) {
                    if ($child->nodeType === \XML_PI_NODE) {
                        /** @noinspection UnusedFunctionResultInspection */
                        $this->document->removeChild($child);

                        break;
                    }
                }
            }
        }

        if (
            $documentFound === false
            &&
            \count($xmlErrors) > 0
        ) {
            $errorStr = 'XML-Errors: ' . \print_r($xmlErrors, true) . ' in ' . \print_r($xml, true);

            if (!$this->reportXmlErrorsAsException) {
                \trigger_error($errorStr, \E_USER_WARNING);
            } else {
                throw new \InvalidArgumentException($errorStr);
            }
        }

        // set encoding
        $this->document->encoding = $this->getEncoding();

        // restore lib-xml settings
        \libxml_clear_errors();
        \libxml_use_internal_errors($internalErrors);
        if (\PHP_VERSION_ID < 80000 && isset($disableEntityLoader)) {
            \libxml_disable_entity_loader($disableEntityLoader);
        }

        return $this->document;
    }

    /**
     * Find list of nodes with a CSS or xPath selector.
     *
     * @param string   $selector
     * @param int|null $idx
     *
     * @return SimpleXmlDomInterface|SimpleXmlDomInterface[]|SimpleXmlDomNodeInterface<SimpleXmlDomInterface>
     */
    public function find(string $selector, $idx = null)
    {
        $xPathQuery = SelectorConverter::toXPath($selector, true, false);

        $xPath = new \DOMXPath($this->document);

        if ($this->autoRegisterXPathNamespaces) {
            foreach ($this->xPathNamespaces as $key => $value) {
                $xPath->registerNamespace($key, $value);
            }
        }

        if ($this->callbackXPathBeforeQuery) {
            $xPathQuery = \call_user_func($this->callbackXPathBeforeQuery, $selector, $xPathQuery, $xPath, $this);
        }

        $nodesList = $xPath->query($xPathQuery);

        $elements = new SimpleXmlDomNode();

        if ($nodesList) {
            foreach ($nodesList as $node) {
                $elements[] = new SimpleXmlDom($node);
            }
        }

        // return all elements
        if ($idx === null) {
            if (\count($elements) === 0) {
                return new SimpleXmlDomNodeBlank();
            }

            return $elements;
        }

        // handle negative values
        if ($idx < 0) {
            $idx = \count($elements) + $idx;
        }

        // return one element
        return $elements[$idx] ?? new SimpleXmlDomBlank();
    }

    /**
     * Find nodes with a CSS or xPath selector.
     *
     * @param string $selector
     *
     * @return SimpleXmlDomInterface[]|SimpleXmlDomNodeInterface<SimpleXmlDomInterface>
     */
    public function findMulti(string $selector): SimpleXmlDomNodeInterface
    {
        return $this->find($selector, null);
    }

    /**
     * Find nodes with a CSS or xPath selector or false, if no element is found.
     *
     * @param string $selector
     *
     * @return false|SimpleXmlDomInterface[]|SimpleXmlDomNodeInterface<SimpleXmlDomInterface>
     */
    public function findMultiOrFalse(string $selector)
    {
        $return = $this->find($selector, null);

        if ($return instanceof SimpleXmlDomNodeBlank) {
            return false;
        }

        return $return;
    }

    /**
     * Find one node with a CSS or xPath selector.
     *
     * @param string $selector
     *
     * @return SimpleXmlDomInterface
     */
    public function findOne(string $selector): SimpleXmlDomInterface
    {
        return $this->find($selector, 0);
    }

    /**
     * Find one node with a CSS or xPath selector or false, if no element is found.
     *
     * @param string $selector
     *
     * @return false|SimpleXmlDomInterface
     */
    public function findOneOrFalse(string $selector)
    {
        $return = $this->find($selector, 0);

        if ($return instanceof SimpleXmlDomBlank) {
            return false;
        }

        return $return;
    }

    /**
     * @param string $content
     * @param bool   $multiDecodeNewHtmlEntity
     *
     * @return string
     */
    public function fixHtmlOutput(string $content, bool $multiDecodeNewHtmlEntity = false): string
    {
        $content = $this->decodeHtmlEntity($content, $multiDecodeNewHtmlEntity);

        return self::putReplacedBackToPreserveHtmlEntities($content);
    }

    /**
     * Return elements by ".class".
     *
     * @param string $class
     *
     * @return SimpleXmlDomInterface[]|SimpleXmlDomNodeInterface<SimpleXmlDomInterface>
     */
    public function getElementByClass(string $class): SimpleXmlDomNodeInterface
    {
        return $this->findMulti(".${class}");
    }

    /**
     * Return element by #id.
     *
     * @param string $id
     *
     * @return SimpleXmlDomInterface
     */
    public function getElementById(string $id): SimpleXmlDomInterface
    {
        return $this->findOne("#${id}");
    }

    /**
     * Return element by tag name.
     *
     * @param string $name
     *
     * @return SimpleXmlDomInterface
     */
    public function getElementByTagName(string $name): SimpleXmlDomInterface
    {
        $node = $this->document->getElementsByTagName($name)->item(0);

        if ($node === null) {
            return new SimpleXmlDomBlank();
        }

        return new SimpleXmlDom($node);
    }

    /**
     * Returns elements by "#id".
     *
     * @param string   $id
     * @param int|null $idx
     *
     * @return SimpleXmlDomInterface|SimpleXmlDomInterface[]|SimpleXmlDomNodeInterface<SimpleXmlDomInterface>
     */
    public function getElementsById(string $id, $idx = null)
    {
        return $this->find("#${id}", $idx);
    }

    /**
     * Returns elements by tag name.
     *
     * @param string   $name
     * @param int|null $idx
     *
     * @return SimpleXmlDomInterface|SimpleXmlDomInterface[]|SimpleXmlDomNodeInterface<SimpleXmlDomInterface>
     */
    public function getElementsByTagName(string $name, $idx = null)
    {
        $nodesList = $this->document->getElementsByTagName($name);

        $elements = new SimpleXmlDomNode();

        foreach ($nodesList as $node) {
            $elements[] = new SimpleXmlDom($node);
        }

        // return all elements
        if ($idx === null) {
            if (\count($elements) === 0) {
                return new SimpleXmlDomNodeBlank();
            }

            return $elements;
        }

        // handle negative values
        if ($idx < 0) {
            $idx = \count($elements) + $idx;
        }

        // return one element
        return $elements[$idx] ?? new SimpleXmlDomNodeBlank();
    }

    /**
     * Get dom node's outer html.
     *
     * @param bool $multiDecodeNewHtmlEntity
     *
     * @return string
     */
    public function html(bool $multiDecodeNewHtmlEntity = false): string
    {
        if (static::$callback !== null) {
            \call_user_func(static::$callback, [$this]);
        }

        $content = $this->document->saveHTML();

        if ($content === false) {
            return '';
        }

        return $this->fixHtmlOutput($content, $multiDecodeNewHtmlEntity);
    }

    /**
     * Load HTML from string.
     *
     * @param string   $html
     * @param int|null $libXMLExtraOptions
     *
     * @return $this
     */
    public function loadHtml(string $html, $libXMLExtraOptions = null): DomParserInterface
    {
        $this->document = $this->createDOMDocument($html, $libXMLExtraOptions);

        return $this;
    }

    /**
     * Load HTML from file.
     *
     * @param string   $filePath
     * @param int|null $libXMLExtraOptions
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function loadHtmlFile(string $filePath, $libXMLExtraOptions = null): DomParserInterface
    {
        if (
            !\preg_match("/^https?:\/\//i", $filePath)
            &&
            !\file_exists($filePath)
        ) {
            throw new \RuntimeException("File ${filePath} not found");
        }

        try {
            if (\class_exists('\voku\helper\UTF8')) {
                $html = \voku\helper\UTF8::file_get_contents($filePath);
            } else {
                $html = \file_get_contents($filePath);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("Could not load file ${filePath}");
        }

        if ($html === false) {
            throw new \RuntimeException("Could not load file ${filePath}");
        }

        return $this->loadHtml($html, $libXMLExtraOptions);
    }

    /**
     * @param string $selector
     * @param int    $idx
     *
     * @return SimpleXmlDomInterface|SimpleXmlDomInterface[]|SimpleXmlDomNodeInterface<SimpleXmlDomInterface>
     */
    public function __invoke($selector, $idx = null)
    {
        return $this->find($selector, $idx);
    }

    /**
     * @param string $xml
     *
     * @return string
     */
    private function removeXPathNamespaces(string $xml): string
    {
        foreach ($this->xPathNamespaces as $key => $value) {
            $xml = \str_replace($key . ':', '', $xml);
        }

        return (string) \preg_replace('#xmlns:?.*=(["\'])(?:.*)\\1#Ui', '', $xml);
    }

    /**
     * Load XML from string.
     *
     * @param string   $xml
     * @param int|null $libXMLExtraOptions
     *
     * @return $this
     */
    public function loadXml(string $xml, $libXMLExtraOptions = null): self
    {
        $this->document = $this->createDOMDocument($xml, $libXMLExtraOptions);

        return $this;
    }

    /**
     * Load XML from file.
     *
     * @param string   $filePath
     * @param int|null $libXMLExtraOptions
     *
     * @throws \RuntimeException
     *
     * @return $this
     */
    public function loadXmlFile(string $filePath, $libXMLExtraOptions = null): self
    {
        if (
            !\preg_match("/^https?:\/\//i", $filePath)
            &&
            !\file_exists($filePath)
        ) {
            throw new \RuntimeException("File ${filePath} not found");
        }

        try {
            if (\class_exists('\voku\helper\UTF8')) {
                $xml = \voku\helper\UTF8::file_get_contents($filePath);
            } else {
                $xml = \file_get_contents($filePath);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("Could not load file ${filePath}");
        }

        if ($xml === false) {
            throw new \RuntimeException("Could not load file ${filePath}");
        }

        return $this->loadXml($xml, $libXMLExtraOptions);
    }

    /**
     * @param callable      $callback
     * @param \DOMNode|null $domNode
     *
     * @return void
     */
    public function replaceTextWithCallback($callback, \DOMNode $domNode = null)
    {
        if ($domNode === null) {
            $domNode = $this->document;
        }

        if ($domNode->hasChildNodes()) {
            $children = [];

            // since looping through a DOM being modified is a bad idea we prepare an array:
            foreach ($domNode->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                if ($child->nodeType === \XML_TEXT_NODE) {
                    /** @noinspection PhpSillyAssignmentInspection */
                    /** @var \DOMText $child */
                    $child = $child;

                    $oldText = self::putReplacedBackToPreserveHtmlEntities($child->wholeText);
                    $newText = $callback($oldText);
                    if ($domNode->ownerDocument) {
                        $newTextNode = $domNode->ownerDocument->createTextNode(self::replaceToPreserveHtmlEntities($newText));
                        $domNode->replaceChild($newTextNode, $child);
                    }
                } else {
                    $this->replaceTextWithCallback($callback, $child);
                }
            }
        }
    }

    /**
     * @param bool $autoRemoveXPathNamespaces
     *
     * @return $this
     */
    public function autoRemoveXPathNamespaces(bool $autoRemoveXPathNamespaces = true): self
    {
        $this->autoRemoveXPathNamespaces = $autoRemoveXPathNamespaces;

        return $this;
    }

    /**
     * @param bool $autoRegisterXPathNamespaces
     *
     * @return $this
     */
    public function autoRegisterXPathNamespaces(bool $autoRegisterXPathNamespaces = true): self
    {
        $this->autoRegisterXPathNamespaces = $autoRegisterXPathNamespaces;

        return $this;
    }

    /**
     * @param callable $callbackXPathBeforeQuery
     *
     * @phpstan-param callable(string $cssSelectorString, string $xPathString, \DOMXPath, \voku\helper\XmlDomParser): string $callbackXPathBeforeQuery
     *
     * @return $this
     */
    public function setCallbackXPathBeforeQuery(callable $callbackXPathBeforeQuery): self
    {
        $this->callbackXPathBeforeQuery = $callbackXPathBeforeQuery;

        return $this;
    }

    /**
     * @param callable $callbackBeforeCreateDom
     *
     * @phpstan-param callable(string $xmlString, \voku\helper\XmlDomParser): string $callbackBeforeCreateDom
     *
     * @return $this
     */
    public function setCallbackBeforeCreateDom(callable $callbackBeforeCreateDom): self
    {
        $this->callbackBeforeCreateDom = $callbackBeforeCreateDom;

        return $this;
    }

    /**
     * @param bool $reportXmlErrorsAsException
     *
     * @return $this
     */
    public function reportXmlErrorsAsException(bool $reportXmlErrorsAsException = true): self
    {
        $this->reportXmlErrorsAsException = $reportXmlErrorsAsException;

        return $this;
    }
}
