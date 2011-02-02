<?php 
#########################
###  DAX HTML Scrubber

###      
###    ###  What tags I want to Allow
###    $simple_config = array( 'allowed_tags' => array( 'div','hr','br','p','a','b','strong','i','em','span','u','ul','ol','li','style'),
###                            'allowed_attrs' => array('class'),
###                            'allowed_styles' => array( 'font-weight','font-style','text-decoration'),
###                            'options' => array( 'the_works' => true )
###                            );
###      
###    ###  What tags I want to Allow
###    $advanced_config =
###        array( 'allowed_tags' => array( 'div' => true,
###                                        'hr' => true,
###                                        'br' => true,
###                                        'p' => true,
###                                        'a' => true,
###                                        'b' => true,
###                                        'strong' => true,
###                                        'i' => array( 'disallowed_styles' => array( 'font-style' => true ) ),
###                                        'em' => array( 'disallowed_styles' => array( 'font-style' => true ) ),
###                                        'span' => true,
###                                        'u' => true,
###                                        'ul' => true,
###                                        'ol' => true,
###                                        'li' => true,
###                                        'style' => array( 'allowed_styles' => array( 'border' => true,
###                                                                                     'margin' => true,
###                                                                                     'float' => true
###                                                                                     )
###                                                          )
###                                        ),
###               'allowed_attrs' => array( 'class' => true ),
###               'allowed_styles' => array( 'font-weight' => true,
###                                          'font-style' => true,
###                                          'text-decoration' => array( 'regexp' => 'underline' )
###                                          ),
###               'options' => array( 'collapse_tables_nicely' => true,
###                                   'honor_td_brs_like_rows' => true,
###                                   'max_consec_blank_lines' => 1,
###                                   'cull_empty_inline_tags' => true,
###                                   'transform_urls_into_anchors' => true,
###                                   'transform_emails_into_anchors' => true,
###                                   'remove_useless_parents' => true
###                                   # 'disable_style_attr' => false
###                                   )
###               );
###       
###    ###  Simple Scrub your element
###    echo dax_scrub( $_REQUEST['input_html'], $simple_config );
###      
###      

###  If we don't have debug.inc.php, stub some functions
if ( ! function_exists('START_TIMER') ) {
    function START_TIMER() {};
    function PAUSE_TIMER() {};
    function RESUME_TIMER() {};
    function END_TIMER() {};
    function bug() {};
}

###  Globals
$dax_neverNested =
      array( 'br' => true,
             'hr' => true,
             'img' => true,
             'input' => true,
             'link' => true,
             'meta' => true,
             'param' => true,
             'area' => true
             );
$dax_inlineElements =
    array( 'span' => true,
           'b' => true,
           'i' => true,
           'em' => true,
           'strong' => true,
           'u' => true,
           'a' => true,
           'q' => true,
           'code' => true,
           'var' => true,
           'kbd' => true,
           'samp' => true,
           'abbr' => true,
           'acronym' => true,
           'del' => true,
           'ins' => true,
           'dfn' => true
           );
$dax_freeElementAttrs =
    array( 'img' => array( 'allowed_attrs' => array( 'src' => true,
                                                     'alt' => true
                                                     )
                           ),
           'a' => array( 'allowed_attrs' => array( 'href' => true
                                                   )
                         )
           );

$dax_scrub_tree = array();
$dax_scrub_this_node = &$dax_scrub_tree;
$dax_scrub_final_html = '';
function dax_scrub($html, $scrub_config) { START_TIMER('dax_scrub'); global $dax_neverNested, $dax_inlineElements, $dax_freeElementAttrs, $dax_scrub_tree, $dax_scrub_this_node, $dax_scrub_final_html;
    ###  Handle Options
    if ( ! isset( $scrub_config ) )                    $scrub_config = array();
    if ( ! isset( $scrub_config['allowed_tags'] ) )    $scrub_config['allowed_tags'] = array();
    if ( ! isset( $scrub_config['allowed_attrs'] ) )   $scrub_config['allowed_attrs'] = array();
    if ( ! isset( $scrub_config['allowed_styles'] ) )  $scrub_config['allowed_styles'] = array();
    if ( ! isset( $scrub_config['options'] ) )         $scrub_config['options'] = array();
    ###  Convert PHP-NUMERIC Array config items into objects
    foreach (array_keys( $scrub_config ) as $i) {
        if ( isset( $scrub_config[$i][0] ) ) {
            $newobj = array();
            foreach ($scrub_config[$i] as $key => $val) { if ( is_int( $key ) ) { $newobj[$val] = true; } else { $newobj[$key] = $val; } }
            $scrub_config[$i] = $newobj;
        }
    }
    ###  Shortcut
    if ( isset( $scrub_config['options']['the_works'] ) ) {
        $scrub_config['options']['collapse_tables_nicely'] = true;
        $scrub_config['options']['honor_td_brs_like_rows'] = true;
        $scrub_config['options']['max_consec_blank_lines'] = 1;
        $scrub_config['options']['cull_empty_inline_tags'] = true;
        $scrub_config['options']['transform_urls_into_anchors'] = true;
        $scrub_config['options']['transform_emails_into_anchors'] = true;
        $scrub_config['options']['remove_useless_parents'] = true;
    }


    #########################
    ###  First Step, Cull some tags and data with Regex to get it out of the way
    START_TIMER('DAX_SCRUB_1st_step_regexes'); 
    $html = preg_replace('/<\!--.*?-->/si', '', $html);                          ###  HTML Comments
    $html = preg_replace('/<\?.*?\?>/si', '', $html);                            ###  PHP Tag (REALLY Simple Syntax, not secure...)
    $html = preg_replace('/<\%.*?\%>/si', '', $html);                            ###  ASP Tag (REALLY Simple Syntax, not secure...)

###  WOW!  This causes FF and Chrome at least to go into an infinite spiral of lockup even tho this regex is nearly identical to these others...
###  $html = preg_replace('/<\!([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>/', '', $html); ###  <! ... > Tags
    $html = preg_replace('/<\![^>]*>/', '', $html); ###  <! ... > Tags ==> Simple but non-Thourough workaround for the bug...

    $html = preg_replace('/<script([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>.*?<\/script([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>/si', '', $html);
    if ( ! isset( $scrub_config['allowed_tags']['style'] ) )    $html = preg_replace('/<style([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>.*?<\/style([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>/si', '', $html);
    if ( ! isset( $scrub_config['allowed_tags']['textarea'] ) ) $html = preg_replace_callback('/<textarea(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>(.*?)<\/textarea(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>/si', 'dax_reduce_tag_contents_to_html_entites', $html);
    if ( ! isset( $scrub_config['allowed_tags']['xmp'] ) ) $html = preg_replace_callback('/<xmp(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>(.*?)<\/xmp(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>/si', 'dax_reduce_tag_contents_to_html_entites', $html); 
    if ( ! isset( $scrub_config['allowed_tags']['pre'] ) ) $html = preg_replace_callback('/<pre(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>(.*?)<\/pre(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>/si', 'dax_reduce_tag_contents_to_html_entites', $html);

    ###  FEATURE: Honor TD BR's like Rows
    if ( isset( $scrub_config['options']['honor_td_brs_like_rows'] ) ) {
        $html = preg_replace_callback('/<tr[^>]*>[^<]*(<(?!\/?tr)[^>]+>[^<]*)*<\/tr>/i', 'dax_honor_td_brs_like_rows', $html); 
    }
    ###  FEATURE: Collapse Tables Nicely
    if ( isset( $scrub_config['options']['collapse_tables_nicely'] ) ) {
        ###  </th>   => <br>
        ###  </tr>   => <br>
        ###  </td>   => space space space
        $html = preg_replace('/<\/t[hr][^>]*>/', "<br>", $html);
        $html = preg_replace('/<\/td[^>]*>/', "   " , $html);
    }
    END_TIMER('DAX_SCRUB_1st_step_regexes'); 

    #########################
    ###  Second, Securely Get the list of tags and content
    START_TIMER('DAX_SCRUB_2nd_step_tag_split'); 
    $first_split = explode('>',$html);  $last_content = array_pop($first_split);
    $tags = array();
    $current_tag = array('','');
    for ($i = 0;$i < count( $first_split );$i++) {
        ###  If This is a close angle with no (legal) open angle preceeding it, then treat it as content
        if ( ! preg_match('/<[a-z\/]/i',$first_split[$i],$m, PREG_OFFSET_CAPTURE) ) { $current_tag[0] .= $first_split[$i] . '>';  continue; } $x = $m[0][1];
        $current_tag[0] .= substr( $first_split[$i], 0,$x );
        $current_tag[1] .= substr( $first_split[$i], $x ) . '>';

        ###  If we can detect quote pairs ending in an un-closed quote pair, then we need to read the next item and re-try, etc...
        while ( preg_match('/^<([^\'\"]*(\'[^\']*\'|\"[^\"]*\"))*([^\'\"]*(\'[^\']*|\"[^\"]*))>$/i', $current_tag[1], $m) && $i < count( $first_split ) ) {
            $current_tag[1] .= $first_split[++$i] . '>';
        }

        ###  Finally, add the tag to the queue
        $current_tag[2] = $i;  array_push( $tags, $current_tag );  $current_tag = array('','');
    }
    ###  Prepare a Final FLAG TO SIGNAL WHEN WE'RE DONE
    array_push( $tags, array($last_content,'') );
    END_TIMER('DAX_SCRUB_2nd_step_tag_split'); 

    #########################
    ###  Third, Crawl, Order and Apply Rules
    START_TIMER('DAX_SCRUB_3rd_step_tree_crawl'); 
    $dax_scrub_final_html = '';
    $dax_scrub_tree = 
        array( 'tagname' => 'root',
               'is_root_node' => true,
               'children' => array(),
               );
    $watch_stuff = 
        array( 'consec_newlines' => 0,
               );
    $dax_scrub_this_node = &$dax_scrub_tree;
    for ($i = 0;$i < count( $tags );$i++) {
        ###  Look for, FLAG TO SIGNAL WHEN WE'RE DONE
        if ( $tags[$i][1] == '' ) { 

            ###  Do Final HTML Flatten...
            $dax_scrub_this_node = &$dax_scrub_tree;
            array_push( $dax_scrub_tree['children'], array( 'pre_tag_content' => '') );
            $GLOBALS['dax_scrub_this_node'] = &$dax_scrub_this_node;  PAUSE_TIMER('DAX_SCRUB_3rd_step_tree_crawl');  dax_scrub_flatten_stuff($watch_stuff);  RESUME_TIMER('DAX_SCRUB_3rd_step_tree_crawl');


            ###  Now, tack on Last possible piece
            $dax_scrub_final_html .= $tags[$i][0];
            break;
        }

        $m = null; # Keep the line count the same
        ###  The remote case, the tag was malformed like: </ > wth no word char, but with a slash
        if (! preg_match('/^<\/?(\w+)/', $tags[$i][1], $m)) { $tags[$i+1][0] = $tags[$i][0] . $tags[$i+1][0]; continue; } ###  keep the content, drop the tag
        
        ###  SCRUB the tag, attrbutes, styles, etc
        PAUSE_TIMER('DAX_SCRUB_3rd_step_tree_crawl');  $scrub_result = dax_scrub_tag( strtolower( $m[1] ), $tags[$i][1], $scrub_config );  RESUME_TIMER('DAX_SCRUB_3rd_step_tree_crawl');
        ###  If the Scrub removed the tag, ignore the tag, and move on...
        if ( empty( $scrub_result[0] ) ) { $tags[$i+1][0] = $tags[$i][0] . $tags[$i+1][0]; continue; } ###  keep the content, drop the tag
        ###  Otherwise, continue building our own fake DOM
        unset ($node);  PAUSE_TIMER('DAX_SCRUB_3rd_step_tree_crawl');  $node = 
            array( 'tagname' => strtolower( $m[1] ),
                   'pre_tag_content' => dax_scrub_content($tags[$i][0], $scrub_config),
                   'tag' => $scrub_result[1],
                   'pre_close_tag_content' => '',
                   'close_tag' => '',
                   'flat' => $scrub_result[2],
                   'children' => array(),
                   );  RESUME_TIMER('DAX_SCRUB_3rd_step_tree_crawl');
        
        ###  FEATURE: Maximum Consecutive Blank Lines : Reset whenever we see content
        if ( ! preg_match('/^\s*(&nbsp;\s*)*$/', $node['pre_tag_content'], $m) )
            $watch_stuff['consec_newlines'] = 0;

        ###  OPEN Tag
        if ( substr( $tags[$i][1], 1, 1 ) != '/' ) { # bug('OPEN tag <'. $node['tagname'] .'>');
            $node['parent'] = &$dax_scrub_this_node;
            $dax_scrub_this_node['children'][] = &$node;

            ###  Go INTO this tag, Unless this is "neverNested" or is Self-Closed
            if ( ! isset( $dax_neverNested[ $node['tagname'] ] ) ###  Like <br>, etc
                 && substr( $tags[$i][1], strlen( $tags[$i][1] ) - 2, 1 ) != '/'
               ) {
                $dax_scrub_this_node = &$node;
            }
            ###  Self-closing tag...
            else { 
#  bug('SELF CLOSING TAG: "'. $node['tag'] .'" ('. $scrub_config['options']['cull_empty_inline_tags'] .') ('. $dax_inlineElements[ $node['tagname'] ] .'), pre-content: "'. substr( $node['pre_tag_content'], 0,25 ) .'..."');
                ###  FEATURE: Cull Empty Inline Tags
                if ( isset( $scrub_config['options']['cull_empty_inline_tags'] )
                     && isset( $dax_inlineElements[ $node['tagname'] ] )                   ###  If it is an inline element
                   ) {
#  bug('Culling Empty Inline ('. $node['tagname'] .') tag, preceeded by: "'. substr( $node['pre_tag_content'], 0,25 ) .'..."');
                    array_pop($dax_scrub_this_node['children']); ###  Just pop the child and forget it...
                    $tags[$i+1][0] = $node['pre_tag_content'] . $tags[$i+1][0]; continue; ###  keep the content, drop the tag
                }

                ###  FEATURE: Maximum Consecutive Blank Lines
                if ( isset( $scrub_config['options']['max_consec_blank_lines'] )
                     && ( $node['tagname'] == 'br' || $node['tagname'] == 'p' )
                   ) $watch_stuff['consec_newlines']++;
                if ( $watch_stuff['consec_newlines'] > ( $scrub_config['options']['max_consec_blank_lines'] + 1 ) ) {
                    array_pop($dax_scrub_this_node['children']);
                    $watch_stuff['consec_newlines']--;
                }

                ###  Flatten DOM to HTML string as we go to save memory (On each close tag)
                $GLOBALS['dax_scrub_this_node'] = &$dax_scrub_this_node;  PAUSE_TIMER('DAX_SCRUB_3rd_step_tree_crawl');  dax_scrub_flatten_stuff($watch_stuff);  RESUME_TIMER('DAX_SCRUB_3rd_step_tree_crawl');
            }
        }
        ###  CLOSE Tag
        else { # bug('CLOSE tag <'. $node['tagname'] .'>');
            ###  Which Tag does this Close?  Loop back 'til we find it (because it might NOT be THIS one...)
            $test_parent = &$dax_scrub_this_node;
            while ( $test_parent['tagname'] != $node['tagname'] && ! isset( $test_parent['is_root_node'] )  && ! $test_parent['is_root_node'] ) { /* bug("MISSING parent <". $test_parent['tagname'] .">"); */  $test_parent = &$test_parent['parent']; }
            ###  If we have a CLOSE, that didn't match an OPEN, ignore the tag
            if ( isset( $test_parent['is_root_node'] ) ) { $tags[$i+1][0] = $tags[$i][0] . $tags[$i+1][0]; continue; } ###  keep the content, drop the tag

            ###  Hook for Stylesheet Scrubbing
            if ( $node['tagname'] == 'style' ) { PAUSE_TIMER('DAX_SCRUB_3rd_step_tree_crawl');  $node['pre_tag_content'] = dax_scrub_stylesheet( $node['pre_tag_content'], $scrub_config);  RESUME_TIMER('DAX_SCRUB_3rd_step_tree_crawl'); }

            ###  Record the Close Tag, Switch to the new Parent
            $test_parent['pre_close_tag_content'] = $node['pre_tag_content'];
            $test_parent['close_tag'] = $node['tag'];
            $dax_scrub_this_node = &$test_parent['parent'];

            ###  FEATURE: Remove Useless Parents
            if ( isset( $scrub_config['options']['remove_useless_parents'] )
                 && $test_parent['flat']                                                                  ###  If this parent is flat (not adding style or attributes)
                 && count( $test_parent['children'] ) == 1                                                ###  And it only has one child
                 && preg_match('/^\s*(&nbsp;\s*)*$/', $test_parent['children'][0]['pre_tag_content'], $m) ###  The content inside these 2 tags ...
                 && preg_match('/^\s*(&nbsp;\s*)*$/', $test_parent['pre_close_tag_content'], $m)          ###  ...  is all whitespace
                 && ( $test_parent['children'][0]['tagname'] == $test_parent['tagname']                   ###  And it's child is the same tag as itself
                      || $test_parent['tagname'] == 'span'                                                ###  OR, it's a style-less inline SPAN tag
                      || $test_parent['tagname'] == 'a'                                                   ###  OR, it's a style-less inline A tag
                    )
               ) {
                ###  Splice the Generations...
                $test_parent['children'][0]['pre_tag_content'] = $test_parent['pre_tag_content'] . $test_parent['children'][0]['pre_tag_content'];
                $test_parent['parent']['children'][ count( $test_parent['parent']['children'] ) - 1 ] = &$test_parent['children'][0];
                $test_parent['children'][0]['parent'] = &$test_parent['parent'];
                ###  Finally, for steps below, pretend that our child IS us
                $test_parent = &$test_parent['children'][0];
            }
            else if ( isset( $scrub_config['options']['remove_useless_parents'] )
                      && $test_parent['flat']                                               ###  If this parent is flat (not adding style or attributes)
                      && count( $test_parent['children'] ) == 0                               ###  And there are NO children
                      && ! preg_match('/^\s*(&nbsp;\s*)*$/', $test_parent['pre_close_tag_content'], $m) ###  BUT, there IS CONTENT inside the tag
                      && ( $test_parent['tagname'] == 'span'                                ###  AND it's a style-less inline SPAN tag
                           || $test_parent['tagname'] == 'a'                                ###  OR, it's a style-less inline A tag
                         )
                    ) {
                array_pop($dax_scrub_this_node['children']); ###  Drop the whole open and close tag together
#  bug('Removing Useless parent ('. $test_parent['tagname'] .') tag, pre-content: "'. substr( $test_parent['pre_tag_content'], 0,25 ) .'...", containing: "'. substr( $test_parent['pre_close_tag_content'], 0,25 ) .'..."'
#      . ' AND tacking both these contents before the pre-content of this next tag: "'. $tags[$i+1][1] .'"'
#  );
                $tags[$i+1][0] = $test_parent['pre_tag_content'] . $test_parent['pre_close_tag_content'] . $tags[$i+1][0]; continue; ###  keep the content, drop the tag
            }

            ###  FEATURE: Cull Empty Inline Tags
            if ( isset( $scrub_config['options']['cull_empty_inline_tags'] )
                 && isset( $dax_inlineElements[ $test_parent['tagname'] ] )                     ###  If it is an inline element
                 && count( $test_parent['children'] ) == 0                                       ###  And it has NO children
                 && preg_match('/^\s*(&nbsp;\s*)*$/', $test_parent['pre_close_tag_content'], $m) ###  and the content is all whitespace
               ) {
                array_pop($dax_scrub_this_node['children']); ###  Drop the whole open and close tag together
#  bug('Culling Empty Inline ('. $test_parent['tagname'] .') tag, preceeded by: "'. substr( $test_parent['pre_tag_content'], 0,25 ) .'..."');
                $tags[$i+1][0] = $test_parent['pre_tag_content'] . $tags[$i+1][0]; continue; ###  keep the content, drop the tag
            }

            ###  Flatten DOM to HTML string as we go to save memory (On each close tag)
            $GLOBALS['dax_scrub_this_node'] = &$dax_scrub_this_node;  PAUSE_TIMER('DAX_SCRUB_3rd_step_tree_crawl');  dax_scrub_flatten_stuff($watch_stuff);  RESUME_TIMER('DAX_SCRUB_3rd_step_tree_crawl');

            ###  FEATURE: Maximum Consecutive Blank Lines
            if ( isset( $scrub_config['options']['max_consec_blank_lines'] )
                 && $test_parent['tagname'] == 'p'                                                         ###  When in a P
                 && preg_match('/^\s*(&nbsp;\s*)*$/', $test_parent['pre_tag_content'], $m)                       ###  and the content ...
                 && preg_match('/^\s*(&nbsp;\s*)*$/', $test_parent['pre_close_tag_content'], $m)                       ###  ...  is all whitespace
                 && ( count( $test_parent['children'] ) == 0                                                 ###  and there are no children
                      || ( preg_match('/^\s*(&nbsp;\s*)*$/', $test_parent['children'][0]['pre_tag_content'], $m) ###  OR, at least the children  ...
                           && preg_match('/^\s*(&nbsp;\s*)*$/', $test_parent['children'][0]['pre_close_tag_content'], $m) ###  ...  have no content either
                         )
                    )
               ) $watch_stuff['consec_newlines']++; 
            if ( $watch_stuff['consec_newlines'] > ( $scrub_config['options']['max_consec_blank_lines'] + 1 ) ) {
                array_pop($dax_scrub_this_node['children']);
                $watch_stuff['consec_newlines']--;
            }
        }
    } END_TIMER('DAX_SCRUB_3rd_step_tree_crawl');

    END_TIMER('dax_scrub'); return $dax_scrub_final_html;
}

function dax_reduce_tag_contents_to_html_entites($content) { $content = $content[1];
    return( preg_replace('/&/','&amp;', preg_replace('/>/','&gt;', preg_replace('/</', '&lt;', $content))) );
}

function dax_scrub_flatten_stuff($watch_stuff) { START_TIMER('DAX_SCRUB_flatten'); global $dax_scrub_tree, $dax_scrub_this_node, $dax_scrub_final_html;
    ###  Merge all Children into the last child
    for ($i = 0;$i < count( $dax_scrub_this_node['children'] );$i++) {

        ###  Merge in it's children
        if ( isset(    $dax_scrub_this_node['children'][$i]['children'] ) 
             && count( $dax_scrub_this_node['children'][$i]['children'] ) > 0 
           ) {
            $dax_scrub_this_node_BAK = &$GLOBALS['dax_scrub_this_node'];
            $GLOBALS['dax_scrub_this_node'] = &$dax_scrub_this_node['children'][$i];
            PAUSE_TIMER('DAX_SCRUB_flatten');  dax_scrub_flatten_stuff($watch_stuff);  RESUME_TIMER('DAX_SCRUB_flatten'); 
            $GLOBALS['dax_scrub_this_node'] = &$dax_scrub_this_node_BAK;

            $last_child = $dax_scrub_this_node['children'][$i]['children'][ count( $dax_scrub_this_node['children'][$i]['children'] ) - 1 ];

            ###  Merge the last child into our pre_close_tag_content
            $dax_scrub_this_node['children'][$i]['pre_close_tag_content'] =
                (   $last_child['pre_tag_content']
                  . $last_child['tag']
                  . $last_child['pre_close_tag_content']
                  . $last_child['close_tag']
                  . $dax_scrub_this_node['children'][$i]['pre_close_tag_content']
                );
            $dax_scrub_this_node['children'][$i]['children'] = array(); ###  Then, kill the children
        }

        ###  Merge this tag into the NEXT SIBLING, unless this is the last sibling
        if ( $i < (count( $dax_scrub_this_node['children'] ) - 1) ) {
            $dax_scrub_this_node['children'][$i+1]['pre_tag_content'] = 
                (   $dax_scrub_this_node['children'][$i]['pre_tag_content']
                    . $dax_scrub_this_node['children'][$i]['tag']
                    . $dax_scrub_this_node['children'][$i]['pre_close_tag_content']
                    . $dax_scrub_this_node['children'][$i]['close_tag']
                    . $dax_scrub_this_node['children'][$i+1]['pre_tag_content']
                );
        }
    }

    ###  Then, kill the ones we just flattened into the last child
    if ( count( $dax_scrub_this_node['children'] ) > 1 ) array_splice($dax_scrub_this_node['children'], 0, count( $dax_scrub_this_node['children'] ) - 1);
    ###  If this is the root node, pass this content into the final HTML
    if ( isset( $dax_scrub_this_node['is_root_node'] ) ) {
        $dax_scrub_final_html .= $dax_scrub_this_node['children'][0]['pre_tag_content'];
        $dax_scrub_this_node['children'][0]['pre_tag_content'] = '';
    }
END_TIMER('DAX_SCRUB_flatten'); }

function dax_scrub_tag($tagname, $tag, $scrub_config) { START_TIMER('DAX_SCRUB_tag'); global $dax_neverNested, $dax_freeElementAttrs;
    if ( isset( $scrub_config['allowed_tags'][ $tagname ] ) ) {
        if ( ! is_array( $scrub_config['allowed_tags'][ $tagname ] ) ) $scrub_config['allowed_tags'][ $tagname ] = ( isset( $dax_freeElementAttrs[ $tagname ] ) ? $dax_freeElementAttrs[ $tagname ] : array() );
        if ( ! isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_attrs'] ) )     $scrub_config['allowed_tags'][ $tagname ]['allowed_attrs']  = array();
        if ( ! isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_styles'] ) )    $scrub_config['allowed_tags'][ $tagname ]['allowed_styles'] = array();
        if ( ! isset( $scrub_config['allowed_tags'][ $tagname ]['disallowed_attrs'] ) )  $scrub_config['allowed_tags'][ $tagname ]['disallowed_attrs']  = array();
        if ( ! isset( $scrub_config['allowed_tags'][ $tagname ]['disallowed_styles'] ) ) $scrub_config['allowed_tags'][ $tagname ]['disallowed_styles'] = array();

        ###  Implied Style attr allowed unless globally disabled
        if ( ! isset( $scrub_config['options']['disable_style_attr'] ) &&
             empty( $scrub_config['allowed_tags'][ $tagname ]['allowed_attrs']['style'] )
           ) $scrub_config['allowed_tags'][ $tagname ]['allowed_attrs']['style'] = true;

        ###  Scrub the tag's attributes...                                                                                                                                                 
        $keepAttrs = array();
        $close_tag = ( substr( $tag, strlen( $tag ) - 2, 1) == '/' || isset( $dax_neverNested[ $tagname ] ) ) ? '/>' : '>';
        $open_tag = ( ( substr( $tag, 1, 1 ) == '/') ? '</' : '<') . $tagname;
        if ( preg_match_all('/(\w+)\=(\"[^\"]*\"|\'[^\']*\'|[^\"\s]+)/', substr( $tag, strlen( $open_tag ), strlen( $tag ) - strlen( $open_tag ) - strlen( $close_tag ) ), $m ) == 0 )
            { END_TIMER('DAX_SCRUB_tag');  return array(true,($open_tag . $close_tag),true); }  $attrs = $m[0];

        $styleAttrRules = array();
        ###  Loop thru the attributes
        for ($i = 0;$i < count( $attrs );$i++) {
            $n_v = null; # Keep the line count the same
            if ( ! preg_match('/^([\w-]+)=\"?([^\"]+)\"?$/', $attrs[$i], $n_v) && ! preg_match('/^([\w-]+)=\'?([^\']+)\'?$/', $attrs[$i], $mm) ) continue;
            $attr = strtolower( $n_v[1] );

            ###  Attribute Rules : Simple Allow / Disallow
            if ( ( ! isset( $scrub_config['allowed_attrs'][ $attr ] ) && ! isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_attrs'][ $attr ] ) )
                 || isset( $scrub_config['allowed_tags'][ $tagname ]['disallowed_attrs'][ $attr ] )
               ) continue;
            ###  Attribute Rules : Tag Inspecific Attr Value regexp
            if ( isset( $scrub_config['allowed_attrs'][ $attr ] ) && is_array( $scrub_config['allowed_attrs'][ $attr ] )
                 && ( ( isset( $scrub_config['allowed_attrs'][ $attr ]['regexp'] )
                        && ! preg_match($scrub_config['allowed_attrs'][ $attr ]['regexp'],  $n_v[2], $m)
                      )
                      || ( isset( $scrub_config['allowed_attrs'][ $attr ]['neg_regexp'] )
                           && preg_match($scrub_config['allowed_attrs'][ $attr ]['neg_regexp'],  $n_v[2], $m)
                         )
                    )
               ) continue;
            ###  Attribute Rules : Tag Specific Attr Value regexp
            if ( isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_attrs'][ $attr ] ) && is_array( $scrub_config['allowed_tags'][ $tagname ]['allowed_attrs'][ $attr ] )
                 && ( ( isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_attrs'][ $attr ]['regexp'] )
                        && ! preg_match($scrub_config['allowed_tags'][ $tagname ]['allowed_attrs'][ $attr ]['regexp'],  $n_v[2], $m)
                      )
                      || ( isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_attrs'][ $attr ]['neg_regexp'] )
                           && preg_match($scrub_config['allowed_tags'][ $tagname ]['allowed_attrs'][ $attr ]['neg_regexp'],  $n_v[2], $m)
                         )
                    )
               ) continue;
            
            ###  Parse CSS Styles for Style Rules
            if ( $attr == 'style' ) {
                $rules = preg_split('/\s*;\s*/', $n_v[2]);
                ###  Loop thru STYLE rules                                                                                                                                              
                for ($ii = 0;$ii < count( $rules );$ii++) {
                    $style_n_v = null; # Keep the line count the same;
                    if ( ! preg_match('/^([\w-]+)\s*:\s*(.+)?$/s', $rules[$ii], $style_n_v) ) continue;
                    $style = strtolower( $style_n_v[1] );
            
                    ###  Style Rules : Simple Allow / Disallow
                    if ( ( ! isset( $scrub_config['allowed_styles'][ $style ] ) && ! isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_styles'][ $style ] ) )
                         || $scrub_config['allowed_tags'][ $tagname ]['disallowed_styles'][ $style ]
                       ) continue;
                    ###  Style Rules : Tag Inspecific Style Value regexp
                    if ( isset( $scrub_config['allowed_styles'][ $style ] ) && is_array( $scrub_config['allowed_styles'][ $style ] )
                         && ( ( isset( $scrub_config['allowed_styles'][ $style ]['regexp'] )
                                && ! preg_match($scrub_config['allowed_styles'][ $style ]['regexp'], $style_n_v[2], $m)
                              )
                              || ( isset( $scrub_config['allowed_styles'][ $style ]['neg_regexp'] )
                                   && preg_match($scrub_config['allowed_styles'][ $style ]['neg_regexp'], $style_n_v[2], $m)
                                 )
                            )
                       ) continue;
                    ###  Style Rules : Tag Specific Style Value regexp
                    if ( isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_styles'][ $style ] ) && is_array( $scrub_config['allowed_tags'][ $tagname ]['allowed_styles'][ $style ] )
                         && ( ( isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_styles'][ $style ]['regexp'] )
                                && ! preg_match($scrub_config['allowed_tags'][ $tagname ]['allowed_styles'][ $style ]['regexp'],  $style_n_v[2], $m)
                              )
                              || ( isset( $scrub_config['allowed_tags'][ $tagname ]['allowed_styles'][ $style ]['neg_regexp'] )
                                   && preg_match($scrub_config['allowed_tags'][ $tagname ]['allowed_styles'][ $style ]['neg_regexp'],  $style_n_v[2], $m)
                                 )
                            )
                       ) continue;
            
                    array_push( $styleAttrRules, $rules[$ii] );
                }
            }
            else {
                array_push( $keepAttrs, $attrs[$i] );
            }
        }
        if ( count( $styleAttrRules ) > 0 ) array_push( $keepAttrs, 'style="'. join( $styleAttrRules, '; ' ) .'"');

        if ( count( $keepAttrs ) > 0 ) { END_TIMER('DAX_SCRUB_tag');  return array(true,($open_tag .' '. join( $keepAttrs, ' ' ) . $close_tag),false); }
        END_TIMER('DAX_SCRUB_tag');  return array(true,($open_tag . $close_tag),true);
    }
    else {
        END_TIMER('DAX_SCRUB_tag');  return array(false,'',true);
    }
}

function dax_scrub_stylesheet($content, $scrub_config) {
    if ( ! isset( $scrub_config['allowed_tags']['style']['allowed_styles'] ) )    $scrub_config['allowed_tags']['style']['allowed_styles'] = array();
    if ( ! isset( $scrub_config['allowed_tags']['style']['disallowed_attrs'] ) )  $scrub_config['allowed_tags']['style']['disallowed_attrs']  = array();
    if ( ! isset( $scrub_config['allowed_tags']['style']['disallowed_styles'] ) ) $scrub_config['allowed_tags']['style']['disallowed_styles'] = array();

    $GLOBALS['dax_tmp_scrub_stylesheet_scrub_config'] = &$scrub_config;  return preg_replace_callback('/\{([^\}]+)\}/', 'dax_scrub_stylesheet_replace', $content); 
} function dax_scrub_stylesheet_replace($m) { global $dax_tmp_scrub_stylesheet_scrub_config;  $scrub_config = &$dax_tmp_scrub_stylesheet_scrub_config;  $style_str = $m[1];
    $styleAttrRules = array();
    $rules = preg_split('/\s*;\s*/', $style_str);
    ###  Loop thru STYLE rules
    for ($ii = 0;$ii < count( $rules );$ii++) {
        $style_n_v = null; # Keep the line count the same
        if ( ! preg_match('/^\s*([\w-]+)\s*:\s*(.+?)?$/s', $rules[$ii], $style_n_v) ) continue;
        $style = strtolower( $style_n_v[1] );
        
        ###  Style Rules : Simple Allow / Disallow
        if ( ( ! isset( $scrub_config['allowed_styles'][ $style ] ) && ! isset( $scrub_config['allowed_tags']['style']['allowed_styles'][ $style ] ) )
             || $scrub_config['allowed_tags']['style']['disallowed_styles'][ $style ]
           ) continue;
        ###  Style Rules : Tag Inspecific Style Value regexp
        if ( isset( $scrub_config['allowed_styles'][ $style ] ) && is_array( $scrub_config['allowed_styles'][ $style ] )
             && ( ( isset( $scrub_config['allowed_styles'][ $style ]['regexp'] )
                    && ! preg_match($scrub_config['allowed_styles'][ $style ]['regexp'], $style_n_v[2], $m)
                  )
                  || ( isset( $scrub_config['allowed_styles'][ $style ]['neg_regexp'] )
                       && preg_match($scrub_config['allowed_styles'][ $style ]['neg_regexp'], $style_n_v[2], $m)
                     )
                )
           ) continue;
        ###  Style Rules : Tag Specific Style Value regexp
        if ( isset( $scrub_config['allowed_tags']['style']['allowed_styles'][ $style ] ) && is_array( $scrub_config['allowed_tags']['style']['allowed_styles'][ $style ] )
             && ( ( isset( $scrub_config['allowed_tags']['style']['allowed_styles'][ $style ]['regexp'] )
                    && ! preg_match($scrub_config['allowed_tags']['style']['allowed_styles'][ $style ]['regexp'],  $style_n_v[2], $m)
                  )
                  || ( isset( $scrub_config['allowed_tags']['style']['allowed_styles'][ $style ]['neg_regexp'] )
                       && preg_match($scrub_config['allowed_tags']['style']['allowed_styles'][ $style ]['neg_regexp'],  $style_n_v[2], $m)
                     )
                )
           ) continue;
        
        array_push( $styleAttrRules, $rules[$ii] );
    }
    return '{' . join( $styleAttrRules, '; ' ) .'}';
}
# extra line for JavaScript close function

function dax_scrub_content($content, $scrub_config) {

    ###  FEATURE: Convert URL's into links
    ###  Regex Explanation: We need to match at least one Non-URL-ish char
    ###  after the email before the negative lookahead assertions, otherwise,
    ###  the pattern will simply match one less char of the URL.
    ###  So, the 1-char after the email forces the [^\s\"<>]+ to be greedy...
    ###  Also: The optional prefixes (e.g. [^<]*?) in the negative lookahead 
    ###  are because there may be other attributes in the <a> tag, or spaces
    ###  between the URL and </a> tag...
    if ( isset( $scrub_config['options']['transform_urls_into_anchors'] )
         && strpos($content, '://') !== false
         ) $content = preg_replace('/((https?|ftp)\:\/\/[\w\:\?\!\%\=\-\.\/]+)(([^\"\w\:\?\!\%\=\-\.\/])(?!([^<]*?<)?\/a)|$)/','<a href="$1">$1</a>$3',$content);

    ###  FEATURE: Convert email's into links
    ###  Regex Explanation: We need to match at least one Non-Email-ish char
    ###  after the email before the negative lookahead assertions, otherwise,
    ###  the pattern will simply match one less char of the email address.
    ###  So, the 1-char after the email forces the [^\s\"<>]+ to be greedy...
    ###  Also: The optional prefixes (e.g. [^<]*?) in the negative lookahead 
    ###  are because there may be other attributes in the <a> tag, or spaces
    ###  between the email and </a> tag...
    if ( isset( $scrub_config['options']['transform_emails_into_anchors'] )
         && strpos($content, '@') !== false
       ) $content = preg_replace('/([^\s\&\;\"<>]+\@[^\s\&\;\"<>]+)(\"(?![^<]*?>)|[^\"\w\.\-\+](?!([^<]*?<)?\/a)|$)/', '<a href="mailto:$1">$1</a>$2', $content);

    return $content;
}


###  converting breaks in tables into individual rows
###  <tr><td>($1a)<br/>($2a)...<br/>($na)</td><td>($1b)<br/>($2b)...<br/>($nb)</td></tr>
###  => <tr><td>($1a)</td><td>($1b)</td></tr>...<tr><td>($na)</td><td>($nb)</td></tr>
function dax_honor_td_brs_like_rows($args) { list($row) = $args;
    $new_rows = array();
    ###  drill down into each td
    if ( preg_match_all('/<td[^>]*>[^<]*(?:<(?!\/td)[^>]*>[^<]*)*<\/td>/i', $row, $td_all, PREG_PATTERN_ORDER) == 0 ) return '';
    $td_all = $td_all[0]; # for PHP we only care about the full patterns
    for ($i = 0;$i < count( $td_all );$i++) {
        ###  get all the separated lines
        $td = strip_outer_tags($td_all[$i]);
        $lines = preg_split('/\s*<br[^>]*>\s*/', $td);
        for ($ii = 0;$ii < count( $lines );$ii++) {
            if ( ! isset( $new_rows[$ii] ) ) $new_rows[$ii] = array();
            $new_rows[$ii][$i] = $lines[$ii];
        }
    }
    $ret_str = "";
    for ($i = 0;$i < count( $new_rows );$i++) {
        $ret_str = $ret_str ."<tr><td>" . join( $new_rows[$i], "</td><td>" ) . "</td></tr>";
    }
    return $ret_str;
}

function strip_outer_tags($input) {
    $input = preg_replace('/^<[^>]*>/', "" , $input, 1);
    $input = preg_replace('/<[^>]*>$/', "" , $input, 1);
    return $input;
}
