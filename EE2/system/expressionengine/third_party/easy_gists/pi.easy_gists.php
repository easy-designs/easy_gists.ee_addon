<?php
/*
=====================================================
 Easy Gists - by Easy! Designs, LLC
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
	'pi_name'			=> 'Easy Gists',
	'pi_version'		=> '1.1',
	'pi_author'			=> 'Aaron Gustafson',
	'pi_author_url'		=> 'http://easy-designs.net/',
	'pi_description'	=> 'Embeds a Github Gist into the page',
	'pi_usage'			=> Easy_gists::usage()
);

class Easy_gists {

	# settings
	var $api_endpoint	= 'https://api.github.com/gists/';
	var $js_endpoint	= 'https://gist.github.com/{id}.js{file}';
	var $gist_css 		= 'https://gist.github.com/stylesheets/gist/embed.css';
	var $cache_dir		= '';
	var $cache_expired	= FALSE;
	var $refresh		= 45; // Period between cache refreshes, in minutes
	
	# instance
	var $gist_id		= FALSE;
	var $highlights		= FALSE;
	var $highlight_with	= 'mark';
	var $wrap_with		= array( 'pre.ext', 'code' );
	var $return_data	= '';
	
	
	/**
	 * Easy_gists constructor
	 * sets any overrides and triggers the processing
	 * 
	 * @param str $str - the content to be parsed
	 */
	function __construct( $gist_id=FALSE, $file='' )
	{
		$this->EE =& get_instance();
	
		# cache dir
		$this->cache_dir = APPPATH . 'cache/' . strToLower( __CLASS__ ) . '/';
		if ( ! is_dir( $this->cache_dir ) )
		{
			mkdir( $this->cache_dir );
		}
		
		# default return
		$this->return_data = $this->EE->TMPL->tagdata;
	
		# defaults
		$swap	= array();
		$embed	= 'yes';
		$css	= TRUE;
		$raw	= FALSE;

		# Gist ID?
		if ( $temp = $this->EE->TMPL->fetch_param('id') ) $gist_id = $temp;
		
		# Raw?
		if ( $temp = $this->EE->TMPL->fetch_param('raw') )
		{
			if ( $temp == 'yes' ) $raw = TRUE;
		}
		
		# Highlights (only in raw)?
		if ( $raw )
		{
			if ( $temp = $this->EE->TMPL->fetch_param('wrap_with') ) $this->wrap_with = explode( ',', $temp );
			if ( $temp = $this->EE->TMPL->fetch_param('highlight') ) $this->highlights = explode( ',', $temp );
			if ( is_array( $this->highlights ) )
			{
				if ( $temp = $this->EE->TMPL->fetch_param('highlight_with') ) $this->highlight_with = $temp;
			}
		}
		
		# Which file?
		if ( $temp = $this->EE->TMPL->fetch_param('file') ) $file = $temp;
		
		# embed?
		if ( $temp = $this->EE->TMPL->fetch_param('embed') ) $embed = $temp;
		$embed = ( $embed == 'yes' );
		
		# CSS?
		if ( $temp = $this->EE->TMPL->fetch_param('css') && $temp == 'no' ) $css = FALSE;

		if ( $gist_id )
		{
			$this->gist_id = $gist_id;
			$this->return_data = ( $embed == 'yes' ) ? $this->embed( $file, $raw, $css ) : $this->script( $file );
		}
		
		return $this->return_data;
	}
	# end Easy_gists constructor
	
	// --------------------------------------------------------------------

	/**
	 * Easy_gists::embed()
	 * processes the supplied gist
	 * 
	 * @param string
	 * @param boolean
	 * @param boolean
	 * @return string
	 */
	function embed( $file='', $raw=FALSE, $css=TRUE )
	{
		$gist_html = '';
		
		if ( $raw )
		{
			# create the URL
			$url = $this->api_endpoint . $this->gist_id;
			
			# retrieve the gist
			$json = $this->_retrieve( $url );
			
			# parse the json and retrieve the file
			$gist_html = $this->_parse_json( $json, $file );

			if ( empty( $gist_html ) )
			{
				return FALSE;
			}
		}
		
		# HTML pulled from the embed script
		else
		{
			$url = $this->EE->functions->var_swap(
				$this->js_endpoint,
				array(
					'id'	=> $this->gist_id,
					'file'	=> ( ! empty( $file ) ? '?file=' . $file : '' )
				)
			);
			
			# retrieve the gist
			$script = $this->_retrieve( $url );
			
			# parse the script
			$gist_html = $this->_parse_script( $script, $css );
		}

		return $gist_html;
	}
	# end Easy_gists::embed()

	// --------------------------------------------------------------------

	/**
	 * Easy_gists::script()
	 * creates the Gist script element
	 * 
	 * @param string
	 */
	function script( $file )
	{
		$url = $this->EE->functions->var_swap(
			$this->js_endpoint,
			array(
				'id'	=> $this->gist_id,
				'file'	=> ( ! empty( $file ) ? '?file=' . $file : '' )
			)
		);

		return "<script src='{$url}'></script>";
	}
	# end Easy_gists::script()

	// --------------------------------------------------------------------

	/**
	 * Easy_gists::css() - DEPRECATED
	 */
	function css()
	{
		return "";
	}
	# end Easy_gists::css()


	// --------------------------------------------------------------------

	/**
	 * Easy_gists::_highlight()
	 * Highlights requested lines
	 * 
	 * @param string
	 * @return string
	 */
	function _highlight( $code )
	{
		if ( is_array( $this->highlights ) )
		{
			$lines = explode( PHP_EOL, $code );
			
			foreach ( $lines as $i => $line )
			{
				$line_number = $i + 1;
				
				# highlight the line?
				foreach ( $this->highlights as $h )
				{
					# Range
					if ( strpos( $h, '-' ) !== FALSE )
					{
						# check start & end of the range
						$temp = explode( '-', $h );
					
						if ( $line_number === (int)$temp[0] )
						{
							$lines[$i] = "<{$this->highlight_with}>" . $line;
						}
						elseif ( $line_number === (int)$temp[1] )
						{
							$lines[$i] = $line . "</{$this->highlight_with}>";
						}
					}
					# Just a line
					elseif ( $line_number === (int)$h )
					{
						$lines[$i] = "<{$this->highlight_with}>" . $line . "</{$this->highlight_with}>";
					}
				}
			}
			
			$code = implode( PHP_EOL, $lines );
		}
		return $code;
	}
	# end Easy_gists::_highlight()

	// --------------------------------------------------------------------

	/**
	 * Easy_gists::_wrap()
	 * wraps the code in the requested tags
	 * 
	 * @param string
	 * @param string
	 * @return string
	 */
	function _wrap( $code, $extension )
	{
		$prefix = '';
		foreach ( $this->wrap_with as &$element )
		{
			# code?
			if ( strpos( $element, '.ext' ) !== FALSE )
			{
				$element = str_replace( '.ext', '', $element );
				$prefix .= "<{$element} class='{$extension}'>";
			}
			else
			{
				$prefix .= "<{$element}>";
			}
		}

		$suffix = '';
		foreach ( array_reverse( $this->wrap_with ) as $element )
		{
			$suffix .= "</{$element}>";
		}
		
		return $prefix . $code . $suffix;
	}
	# end Easy_gists::_wrap()

	// --------------------------------------------------------------------

	/**
	 * Easy_gists::_retrieve()
	 * Retrieves a URL from the cache or the internets
	 * 
	 * @param string
	 * @return mixed - string if pulling from cache, FALSE if not
	 */
	function _retrieve( $url )
	{
		# read the cache
		$cached = $this->_read_cache( $url );
		$fresh = FALSE;
		
		# no cache, try loading it
		if ( $this->cache_expired OR ! $cached )
		{
			$this->EE->TMPL->log_item( "Fetching {$url}." );
			$fresh = $this->_fetch( $url );
		}

		# couldn’t load?
		if ( ! $fresh )
		{
			# Did we try to grab new data? Tell them that it failed.
			if (  ! $cached OR $this->cache_expired )
			{
				$this->EE->TMPL->log_item( "Unable to retrieve {$url}." );

				# No cache?
				if ( ! $cached )
				{
					return FALSE;
				}

				$this->EE->TMPL->log_item( "Using stale cache of {$url}." );
			}
			else
			{
				$this->EE->TMPL->log_item( "{$url} retrieved from cache." );
			}

			$fresh = $cached;
		}
		else
		{
			# We have (valid) new data - cache it
			$this->_write_cache( $fresh, $url );			
		}
		
		return $fresh;
	}
	# end Easy_gists::_retrieve()

	// --------------------------------------------------------------------
	
	/**
	 * Easy_gists::_parse_json
	 * Parse the gist JSON
	 *
	 * @access	public
	 * @param	string
	 * @param	boolean
	 * @return	string
	 */
	function _parse_json( $json='{}', $file='' )
	{
		# start empty
		$gist = '';
		
		# JSON!
		if ( function_exists( 'json_decode' ) )
		{
			# decode the object
			$gist = json_decode( $json, TRUE );

			# if the file is provided, grab that
			if ( ! empty( $file ) )
			{
				if ( isset( $gist['files'][$file] ) )
				{
					$raw = $this->_retrieve( $gist['files'][$file]['raw_url'] );
					$extension = array_pop( explode( '.', $gist['files'][$file]['filename'] ) );
					$raw = htmlspecialchars( $raw );
					$raw = $this->_highlight( $raw );
					$gist = $this->_wrap( $raw, $extension );
				}
				else
				{
					$this->EE->TMPL->log_item( "{$file} was not found in the Gist." );
				}
			}
			# otherwise grab them all
			else
			{
				foreach ( $gist['files'] as $file => $data )
				{
					$raw = $this->_retrieve( $data['raw_url'] );
					$extension = array_pop( explode( '.', $data['filename'] ) );
					$raw = htmlspecialchars( $raw );
					$raw = $this->_highlight( $raw );
					$gist .= $this->_wrap( $raw, $extension );
				}
			}
		}
		
		# No JSON!
		else
		{
			$this->EE->TMPL->log_item( "You need JSON support in PHP to use the 'raw' option." );
		}
		
		return $gist;
	}
	# end Easy_gists::_parse_json()
	
	// --------------------------------------------------------------------
	
	/**
	 * Easy_gists::_parse_script
	 * Strips away the JavaScripty stuff
	 *
	 * @access	public
	 * @param	string
	 * @param	boolean
	 * @return	string
	 */
	function _parse_script( $script=FALSE, $css=TRUE )
	{
		if ( $script )
		{
			# remove document.writes
			$script = preg_replace( '/document.write\(\'/i', '', $script );
			$script = preg_replace( '/(*ANYCRLF)\'\)$/m', '', $script );
			# remove the CSS?
			if ( ! $css ) $script = preg_replace( '/<link[^>]+>/', '', $script );
			# remove javascript newlines
			$script = preg_replace( '%(?<!/)\\\\n%', '', $script );
			# reverse javascript escaping
			$script = stripslashes( $script );
			# remove line breaks
			$script = preg_replace( "/[\n\r]/", '', $script );
		}
		else
		{
			$script = '';
		}

		return $script;
	}
	# end Easy_gists::_parse_script()
	
	// --------------------------------------------------------------------
	
	/**
	 * Easy_gists::_read_cache
	 *
	 * Read from cached data
	 *
	 * @access	public
	 * @param	string
	 * @return	mixed - string if pulling from cache, FALSE if not
	 */
	function _read_cache( $url )
	{	
		# Check for cache directory
		if ( ! @is_dir( $this->cache_dir ) )
		{
			return FALSE;
		}
		
		# Check for cache file
        $file = $this->cache_dir . md5( $url );
		if ( ! file_exists( $file ) OR
			 ! ( $fp = @fopen($file, 'rb') ) )
		{
			return FALSE;
		}
		
		# read it
		flock( $fp, LOCK_SH );
		$cache = @fread( $fp, filesize( $file ) );
		flock( $fp, LOCK_UN );
		fclose( $fp );

        # Grab the timestamp from the first line
		$eol = strpos( $cache, "\n" );
		$timestamp = substr( $cache, 0, $eol );
		$cache = trim( ( substr( $cache, $eol ) ) );
		
		# check against the refresh time
		if ( time() > ( $timestamp + ( $this->refresh * 60 ) ) )
		{
			$this->cache_expired = TRUE;
		}
		
        return $cache;
	}

	// --------------------------------------------------------------------
	
	/**
	 * Easy_gists::_write_cache
	 *
	 * Write the cached data
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @return	void
	 */
	function _write_cache( $data, $url )
	{
		# Create it if it does not exist
		if ( ! @is_dir( $this->cache_dir ) )
		{
			if ( ! @mkdir( $this->cache_dir ) )
			{
				return FALSE;
			}
			@chmod( $this->cache_dir );            
		}
		
		# add a timestamp to the top of the file
		$data = time() . "\n" . $data;
		
		# write to the cache
		$file = $this->cache_dir . md5( $url );
		if ( ! $fp = @fopen( $file, 'wb' ) )
		{
			return FALSE;
		}
		flock( $fp, LOCK_EX );
		fwrite( $fp, $data );
		flock( $fp, LOCK_UN );
		fclose( $fp );
		@chmod( $file, 0777 );
	}

	// --------------------------------------------------------------------
	
	/**
	 * Easy_gists::_fetch
	 * Fetches a URL form the internets
	 *
	 * @access	public
	 * @param	string
	 * @return	string
	 */
	function _fetch( $url )
	{
		return file_get_contents( $url );
	}

	// --------------------------------------------------------------------
	
	/**
	 * Easy_gists::usage()
	 * Describes how the plugin is used
	 */
	function usage()
	{
		ob_start(); ?>
This plugin allows you to control how the Gist is rendered in your page. The only required property is the Gist ID:

{exp:easy_gists id="245831"}

would result in the text content of the Gist found at https://gist.github.com/245831.js being printed into the document (all scripting would be removed).

This plugin has several optional parameters:

* embed: accepts "yes" or "no" ("yes" by default) and determines whether the content should be embedded (as opposed to linked via script)
* css: accepts "yes" or "no" ("yes" by default) and is used with embed="yes" to determine whether or not the CSS reference should be stripped from the embedded code (in case you either don't want to use the Gist CSS or you've used {exp:gist:css} in the head of your document)
* file: is the string filename you want the Gist to use (assuming your Gist has multiple files)

	{exp:easy_gists id="245831" file="my.html"}

* raw: accepts "yes" or "no" ("no" by default) and has the script load the raw code (as opposed to the parsed JavaScript contents) **EE2 only**
* wrap_with: the elements you want to wrap around the raw code ("pre.ext,code" by default). Separate multiple elements by a comma. Use ".ext" to add a class equal to the file’s extension to a specific element. **EE2 only**
* highlight: Highlight one or more lines (available in raw mode only). Individual lines should be separated by commas ("10,12"). Multiple lines can be higlighted by setting a start and end, separated by a hyphen ("10-12"). You can combine these. **EE2 only**
* highlight_with: The markup you want to wrap the highlighted lines ("mark" by default). **EE2 only**

{exp:easy_gists id="4622706" file="undoing-tables.scss" raw="yes" highlight="1-8,10"}

<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}
	# end Easy_gists::usage()

} # end Easy_gists

/* End of file pi.easy_gists.php */ 
/* Location: ./system/expressionengine/third_party/easy_gists/pi.easy_gists.php */