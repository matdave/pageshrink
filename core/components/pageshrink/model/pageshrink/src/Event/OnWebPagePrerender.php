<?php
namespace PageShrink\Event;

use JShrink\Minifier;
use PageShrink\Services\StringMinifier;
use voku\helper\HtmlMin;
use voku\helper\HtmlDomParser;

class OnWebPagePrerender extends Event
{
    protected array $cacheOptions = [
        \xPDO::OPT_CACHE_KEY => 'resource',
    ];

    protected bool $cacheable = true;
    public function run()
    {
        if (!$this->getOption('resource_shrink', true)) {
            return;
        }
        $modContentType = 'modContentType';
        if ($this->getVersion() > 2) {
            $modContentType = '\\MODX\\Revolution\\modContentType';
        }
        $contentType = $this->modx->getObject($modContentType, $this->modx->resource->get('content_type'));
        if (empty($contentType) ||
            (
                $contentType->get('mime_type') !== 'text/html' &&
                $contentType->get('mime_type') !== 'text/css' &&
                $contentType->get('mime_type') !== 'text/javascript'
            )
        ) {
            return;
        }
        $this->cacheable = (
            $this->modx->resource->get('cacheable') &&
            $this->getOption('cache_resource_shrink', true)
        );
        $cache = $this->getCache();
        if ($cache) {
            return;
        }
        $dirty = $this->modx->resource->_output;
        if ($contentType->get('mime_type') == 'text/html') {
            $dirty = $this->doJSMin($dirty);
            $dirty = $this->doCSSMin($dirty);
            $dirty = $this->doHtmlMin($dirty);
        }
        if ($contentType->get('mime_type') == 'text/css') {
            $dirty = (new StringMinifier($dirty))->minify();
        }
        if ($contentType->get('mime_type') == 'text/javascript') {
            try {
                $dirty =  (new Minifier())::minify($dirty);
            } catch (\Exception $e) {
                $this->modx->log(\modX::LOG_LEVEL_ERROR, $e->getMessage());
            }
        }
        $this->setCache($dirty);
    }

    protected function cacheKey(): string
    {
        $md5 = md5($this->modx->resource->_output);
        return "{$this->modx->context->key}/resources/{$this->modx->resource->id}.pageshrink.$md5";
    }

    protected function getCache(): bool
    {
        if ($this->cacheable) {
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
        if ($this->cacheable) {
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
        $htmlMin->doOptimizeViaHtmlDomParser($this->getOption('optimize_via_html_dom_parser', true));
        // optimize html via "HtmlDomParser()"
        $htmlMin->doRemoveComments($this->getOption('remove_comments', true));
        // remove default HTML comments (depends on "doOptimizeViaHtmlDomParser(true)")
        $htmlMin->doSumUpWhitespace($this->getOption('sum_up_whitespace', true));
        // sum-up extra whitespace from the Dom (depends on "doOptimizeViaHtmlDomParser(true)")
        $htmlMin->doRemoveWhitespaceAroundTags($this->getOption('remove_whitespace_around_tags', true));
        // remove whitespace around tags (depends on "doOptimizeViaHtmlDomParser(true)")
        $htmlMin->doOptimizeAttributes($this->getOption('optimize_attributes', true));
        // optimize html attributes (depends on "doOptimizeViaHtmlDomParser(true)")
        $htmlMin->doRemoveHttpPrefixFromAttributes($this->getOption('remove_http_prefix_from_attributes', true));
        // remove optional "http:"-prefix from attributes (depends on "doOptimizeAttributes(true)")
        $htmlMin->doKeepHttpAndHttpsPrefixOnExternalAttributes(
            $this->getOption('keep_http_prefix_on_external_attributes', true)
        );
        // keep "http:"- and "https:"-prefix for all external links
        $htmlMin->doRemoveDefaultAttributes($this->getOption('remove_default_attributes', true));
        // remove defaults (depends on "doOptimizeAttributes(true)" | disabled by default)
        $htmlMin->doRemoveDeprecatedAnchorName($this->getOption('remove_anchor_name', true));
        // remove deprecated anchor-jump (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveDeprecatedScriptCharsetAttribute(
            $this->getOption('remove_script_charset_attribute', true)
        );
        // remove deprecated charset-attribute - the browser will use the charset from the HTTP-Header,
        // anyway (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveDeprecatedTypeFromScriptTag(
            $this->getOption('remove_type_from_script_tag', true)
        );
        // remove deprecated script-mime-types (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveDeprecatedTypeFromStylesheetLink(
            $this->getOption('remove_type_from_stylesheet_link', true)
        );
        // remove "type=text/css" for css links (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveDeprecatedTypeFromStyleAndLinkTag(
            $this->getOption('remove_type_from_style_link_tag', true)
        );
        // remove "type=text/css" from all links and styles
        $htmlMin->doRemoveDefaultMediaTypeFromStyleAndLinkTag(
            $this->getOption('remove_default_media_typed_link_tag', true)
        );
        // remove "media="all" from all links and styles
        $htmlMin->doRemoveDefaultTypeFromButton(
            $this->getOption('remove_default_type_from_button', true)
        );
        // remove type="submit" from button tags
        $htmlMin->doRemoveEmptyAttributes($this->getOption('remove_empty_attributes', true));
        // remove some empty attributes (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveValueFromEmptyInput($this->getOption('remove_value_from_empty_input', true));
        // remove 'value=""' from empty <input> (depends on "doOptimizeAttributes(true)")
        $htmlMin->doSortCssClassNames($this->getOption('sort_css_class_names', true));
        // sort css-class-names, for better gzip results (depends on "doOptimizeAttributes(true)")
        $htmlMin->doSortHtmlAttributes($this->getOption('sort_html_attributes', true));
        // sort html-attributes, for better gzip results (depends on "doOptimizeAttributes(true)")
        $htmlMin->doRemoveSpacesBetweenTags($this->getOption('remove_spaces_between_tags', true));
        // remove more (aggressive) spaces in the dom (disabled by default)
        $htmlMin->doRemoveOmittedQuotes($this->getOption('remove_omitted_quotes', true));
        // remove quotes e.g. class="lall" => class=lall
        $htmlMin->doRemoveOmittedHtmlTags($this->getOption('remove_omitted_html_tags', false));
        // remove ommitted html tags e.g. <p>lall</p> => <p>lall
    }

    protected function doJSMin($dirty): string
    {
        $html = new HtmlDomParser($dirty);
        $scripts = $html->find('script');
        foreach ($scripts as $script) {
            if (empty($script->innertext) ||
                $script->getAttribute('type') === 'text/template' ||
                $script->getAttribute('type') === 'application/ld+json'
            ) {
                continue;
            }
            if ($script->getAttribute('src')) {
                continue;
            }
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
            $style->innertext =  (new StringMinifier($style->innertext))->minify();
        }
        return $html->save();
    }
}
