<?php
namespace PageShrink\Event;

use JShrink\Minifier;
use PageShrink\Services\SringMinifier;
use voku\helper\HtmlMin;
use voku\helper\HtmlDomParser;

class OnWebPagePrerender extends Event
{
    protected array $cacheOptions = [
        \xPDO::OPT_CACHE_KEY => 'resource',
    ];
    public function run()
    {
        $cache = $this->getCache();
        if ($cache) {
            return;
        }
        $modContentType = 'modContentType';
        if ($this->getVersion() > 2) {
            $modContentType = '\\MODX\\Revolution\\modContentType';
        }
        $contentType = $this->modx->getObject($modContentType, $this->modx->resource->get('content_type'));
        if (empty($contentType) ||
            $contentType->get('mime_type') !== 'text/html'
        ) {
            return;
        }
        $dirty = $this->modx->resource->_output;
        $dirty = $this->doJSMin($dirty);
        $dirty = $this->doCSSMin($dirty);
        $dirty = $this->doHtmlMin($dirty);
        $this->setCache($dirty);
    }

    protected function cacheKey(): string
    {
        return "{$this->modx->context->key}/resources/{$this->modx->resource->id}.pageshrink";
    }

    protected function getCache(): bool
    {
        if ($this->modx->resource->get('cacheable')) {
            $cache = $this->modx->cacheManager->get($this->cacheKey(), $this->cacheOptions);
            if (!empty($cache)) {
                $this->modx->resource->_output = $cache;
                return true;
            }
        }
        return false;
    }

    protected function setCache(string $dirty)
    {
        if ($this->modx->resource->get('cacheable')) {
            $this->modx->cacheManager->set($this->cacheKey(), $dirty, 0, $this->cacheOptions);
        }
        $this->modx->resource->_output = $dirty;
    }

    protected function doHtmlMin($dirty): string
    {
        $htmlMin = new HtmlMin();
        $this->doHtmlMinOptions($htmlMin);
        return $htmlMin->minify($dirty);
    }

    protected function doHtmlMinOptions(HtmlMin $htmlMin)
    {
        $htmlMin->doOptimizeViaHtmlDomParser();               // optimize html via "HtmlDomParser()"
        $htmlMin->doRemoveComments();                         // remove default HTML comments (depends on "doOptimizeViaHtmlDomParser(true)")
        $htmlMin->doSumUpWhitespace();                        // sum-up extra whitespace from the Dom (depends on "doOptimizeViaHtmlDomParser(true)")
        $htmlMin->doRemoveWhitespaceAroundTags();             // remove whitespace around tags (depends on "doOptimizeViaHtmlDomParser(true)")
        $htmlMin->doOptimizeAttributes();                     // optimize html attributes (depends on "doOptimizeViaHtmlDomParser(true)")
        $htmlMin->doRemoveHttpPrefixFromAttributes();         // remove optional "http:"-prefix from attributes (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveHttpsPrefixFromAttributes();        // remove optional "https:"-prefix from attributes (depends on "doOptimizeAttributes(true)")
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes(); // keep "http:"- and "https:"-prefix for all external links
        $htmlMin->doRemoveDefaultAttributes();                // remove defaults (depends on "doOptimizeAttributes(true)" | disabled by default)
        $htmlMin->doRemoveDeprecatedAnchorName();             // remove deprecated anchor-jump (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveDeprecatedScriptCharsetAttribute(); // remove deprecated charset-attribute - the browser will use the charset from the HTTP-Header, anyway (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveDeprecatedTypeFromScriptTag();      // remove deprecated script-mime-types (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveDeprecatedTypeFromStylesheetLink(); // remove "type=text/css" for css links (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveDeprecatedTypeFromStyleAndLinkTag(); // remove "type=text/css" from all links and styles
        $htmlMin->doRemoveDefaultMediaTypeFromStyleAndLinkTag(); // remove "media="all" from all links and styles
        $htmlMin->doRemoveDefaultTypeFromButton();            // remove type="submit" from button tags
        $htmlMin->doRemoveEmptyAttributes();                  // remove some empty attributes (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveValueFromEmptyInput();              // remove 'value=""' from empty <input> (depends on "doOptimizeAttributes(true)")
        $htmlMin->doSortCssClassNames();                      // sort css-class-names, for better gzip results (depends on "doOptimizeAttributes(true)")
        $htmlMin->doSortHtmlAttributes();                     // sort html-attributes, for better gzip results (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveSpacesBetweenTags();                // remove more (aggressive) spaces in the dom (disabled by default)
        $htmlMin->doRemoveOmittedQuotes();                    // remove quotes e.g. class="lall" => class=lall
        $htmlMin->doRemoveOmittedHtmlTags();                  // remove ommitted html tags e.g. <p>lall</p> => <p>lall
    }

    protected function doJSMin($dirty): string
    {
        $html = new HtmlDomParser($dirty);
        $scripts = $html->find('script');
        foreach ($scripts as $script) {
            try {
                $script->innertext =  (new Minifier())::minify($script->innertext);
            } catch (\Exception $e) {
                $this->modx->log(\modX::LOG_LEVEL_ERROR, $e->getMessage());
            }
        }
        return $html->save();
    }

    protected function doCSSMin($dirty): string
    {
        $html = new HtmlDomParser($dirty);
        $styles = $html->find('style');
        foreach ($styles as $style) {
            $style->innertext =  new SringMinifier($style->innertext);
        }
        return $html->save();
    }
}
