Easy Gists EE Addon
===============================

An ExpressionEngine plugin to allow for embedding of Github Gists.

The API
-------

This plugin allows you to control how the Gist is rendered in your page. The only required property is the Gist ID:

	{exp:easy_gists id="245831"}

would result in the text content of the Gist found at https://gist.github.com/245831.js being printed into the document (all scripting would be removed).

This plugin has several optional parameters:

 - `embed`: accepts "yes" or "no" ("yes" by default) and determines whether the content should be embedded (as opposed to linked via script)
 - `css`: accepts "yes" or "no" ("yes" by default) and is used with `embed="yes"` to determine whether or not the CSS reference should be stripped from the embedded code (in case you either don't want to use the Gist CSS or you've used `{exp:gist:css}` in the head of your document)
 - `file`: is the string filename you want the Gist to use (assuming your Gist has multiple files)
 - `raw`: accepts "yes" or "no" ("no" by default) and has the script load the raw code (as opposed to the parsed JavaScript contents) *EE2 only*
 - `wrap_with`: the elements you want to wrap around the raw code ("pre.ext,code" by default). Separate multiple elements by a comma. Use ".ext" to add a `class` equal to the file’s extension to a specific element. *EE2 only*
 - `highlight`: Highlight one or more lines (available in raw mode only). Individual lines should be separated by commas ("10,12"). Multiple lines can be higlighted by setting a start and end, separated by a hyphen ("10-12"). You can combine these. *EE2 only*
 - `highlight_with`: The markup you want to wrap the highlighted lines ("mark" by default). *EE2 only*

You can also embed the Gist CSS from Github directly by using

	{exp:easy_gists:css}

It has one optional parameter, media, which you can use to specify the media to direct the CSS to (undefined/all by default):

	{exp:easy_gists:css media="screen"}

License
-------

pi.easy_gists.php is distributed under the liberal MIT License.