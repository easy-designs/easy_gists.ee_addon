<?php
/*
=====================================================
 Gist - by Easy! Designs, LLC
-----------------------------------------------------
 http://www.easy-designs.net/
=====================================================
 This extension was created by Aaron Gustafson
 - aaron@easy-designs.net based on work by
 Andrew Hutchings - http://andrewhutchings.com
 This work is licensed under the MIT License.
=====================================================
 File: pi.gist.php
-----------------------------------------------------
 Purpose: Embeds a Github Gist into the page
 (rather than using JavaScript)
=====================================================
*/

$plugin_info = array(
  'pi_name'        => 'Gist',
  'pi_version'     => '1.0',
  'pi_author'      => 'Aaron Gustafson',
  'pi_author_url'	 => 'http://easy-designs.net/',
  'pi_description' => 'Embeds a Github Gist into the page',
  'pi_usage'       => Gist::usage()
);

class Gist {

  # properties
  var $URL         = 'http://gist.github.com/{id}.js{file}';
  var $return_data = '';
  
  /**
   * Gist constructor
   * sets any overrides and triggers the processing
   * 
   * @param str $str - the content to be parsed
   */
  function Gist()
  {
    # globals
    global $FNS, $TMPL;
    
    # locals
    $file     = '?file=';
    $swap     = array();
    $embed    = 'yes';
    $css      = TRUE;
    
    # get any tag overrides
    $swap['id']   = ( $temp = $TMPL->fetch_param('id') ) ? $temp : FALSE;
    $swap['file'] = ( $temp = $TMPL->fetch_param('file') ) ? $file . $temp : '';
    if ( $temp = $TMPL->fetch_param('embed') ) $embed = $temp;
    if ( $temp = $TMPL->fetch_param('css') AND $temp == 'no' ) $css = FALSE;
    
    if ( $swap['id'] )
    {
      $this->URL = $FNS->var_swap( $this->URL, $swap );
      $this->return_data = ( $embed == 'yes' ) ? $this->embed( $css ) : $this->script();
    }
  } # end Gist constructor
  
  /**
   * Gist::embed()
   * processes the supplied gist
   */
  function embed( $css=TRUE )
  {
    # trim
    $str = file_get_contents( $this->URL );
    if ( $str != FALSE )
    {
      # remove document.writes
      $str = preg_replace( '/document.write\(\'/i', '', $str );
      $str = preg_replace( '/(*ANYCRLF)\'\)$/m', '', $str );
      # remove the CSS?
      if ( ! $css ) $str = preg_replace( '/<link rel="stylesheet"[^>]+>/', '', $str );
      # remove javascript newlines
      $str = preg_replace( '%(?<!/)\\\\n%', '', $str );
      # reverse javascript escaping
      $str = stripslashes( $str );
      # remove line breaks
      $str = preg_replace( "/[\n\r]/", '', $str );
    }
    return $str;
  } # end Gist::render()
    
  /**
   * Gist::script()
   * creates the Gist script element
   */
  function script()
  {
    return '<script type="text/javascript" src="' . $this->URL . '"></script>';
  } # end Gist::script()

  /**
   * Gist::css()
   * generates the HTML link element pointing to the Gist CSS file
   */
  function css()
  {
    # determine the media
    global $TMPL;
    $media = ( $temp = $TMPL->fetch_param('media') ) ? 'media="' . $temp . '"' : '';
    
    return ( '<link rel="stylesheet" type="text/css" ' . $media .
             ' href="http://gist.github.com/stylesheets/gist/embed.css" />' );
  } # end Gist::css()

  /**
   * Gist::usage()
   * Describes how the plugin is used
   */
  function usage()
  {
    ob_start(); ?>
This plugin allows you to control how the Gist is rendered in your page. The only required property is the Gist ID:

{exp:gist id="245831"}

would result in the text content of the Gist found at http://gist.github.com/245831.js being printed into the document (all scripting would be removed).

This plugin has several optional parameters:

* embed: accepts "yes" or "no" ("yes" by default) and determines whether the content should be embedded
* css: accepts "yes" or "no" ("yes" be default) and is used with embed="yes" to determine whether or not the CSS reference should be stripped from the embedded code (in case you either don't want to use the Gist CSS or you've used {exp:gist:css} in the head of your document)
* file: is the string filename you want the gist to use

You can also embed the Gist CSS from Github directly by using

{exp:gist:css}

It has one optional parameter, media, which you can use to specify the media to direct the CSS to (undefined/all by default):

{exp:gist:css media="screen"}

<?php
    $buffer = ob_get_contents();
    ob_end_clean();
    return $buffer;
  } # end Gist::usage()

} # end Gist

?>
