# MODX PageShrink

MODX PageShrink is a MODX Revolution Extra that allows you to shrink the size of your MODX pages by removing unnecessary 
whitespace and comments from the HTML output.

## Installation

1. Download the latest release from the [MODX Extras Repository](http://modx.com/extras/package/pageshrink) or via the 
MODX Package Browser.
2. Install the package via the MODX Package Manager.

## System Settings

| Key                              | Default | Description                               |
|----------------------------------|---------|-------------------------------------------|
| pageshrink.cache_resource_shrink | 1       | Cache the shrunk version of the resource. |

## Usage

MODX PageShrink is enabled by default. The shrunk version of the resource is also cached by default. You can disable the 
cache globally by setting the `pageshrink.cache_resource_shrink` system setting to `0`.

It is recommended to keep the page cache enabled. If you have specific resources that you do not want to cache, you can 
disable the cache for those resources by setting the `cacheable` property to `0` in the resource's settings. This would 
apply to things like forms, pages with pagination, etc., where the painted content of the page will change based on a 
user interaction or request variable.