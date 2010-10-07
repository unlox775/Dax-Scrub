
/////////////////////////
///  DAX HTML Scrubber

/* 
 *  ///  What tags I want to Allow
 *  var simple_config = { allowed_tags   : ['div','hr','br','p','a','b','strong','i','em','span','u','ul','ol','li','style'],
 *                        allowed_attrs  : ['class'],
 *                        allowed_styles : ['font-weight,'font-style','text-decoration'],
 *                        options : { the_works: true }
 *                      };
 *
 *  ///  What tags I want to Allow
 *  var advanced_config =
 *      { allowed_tags : { div : true,
 *                         hr : true,
 *                         br : true,
 *                         p  : true,
 *                         a  : true,
 *                         b  : true,
 *                         strong : true,
 *                         i  : { disallowed_styles : { 'font-style' : true } },
 *                         em : { disallowed_styles : { 'font-style' : true } },
 *                         span : true,
 *                         u  : true,
 *                         ul : true,
 *                         ol : true,
 *                         li : true,
 *                         style : { allowed_styles : { 'border' : true,
 *                                                      'margin' : true,
 *                                                      'float' : true
 *                                                    }
 *                                 }
 *                       },
 *        allowed_attrs : { 'class' : true },
 *        allowed_styles : { 'font-weight' : true,
 *                           'font-style'  : true,
 *                           'text-decoration' : { 'regexp' : 'underline' }
 *                         },
 *        options : { collapse_tables_nicely : true,
 *                    honor_td_brs_like_rows : true,
 *                    max_consec_blank_lines : 1,
 *                    cull_empty_inline_tags : true,
 *                    transform_urls_into_anchors : true,
 *                    transform_emails_into_anchors : true,
 *                    remove_useless_parents : true
 *                    //                    disable_style_attr : false
 *                  }
 *      };
 *  
 *  /// Simple Scrub your element
 *  elm.innerHTML = dax_scrub( elm.innerHTML, scrub_config );
 *  
 */

//  Globals
var dax_neverNested =
    { br : true,
      hr : true,
      img : true,
      input : true,
      link : true,
      meta : true,
      param : true,
      area : true
    };
var dax_inlineElements =
    { span : true,
      b : true,
      i : true,
      em : true,
      strong : true,
      u : true,
      a : true,
      q : true,
      code : true,
      'var' : true,
      kbd : true,
      samp : true,
      abbr : true,
      acronym : true,
      del : true,
      ins : true,
      dfn : true
    };
var dax_freeElementAttrs =
    { img : { allowed_attrs : { src : true,
                                alt : true
                              }
            },
      a : { allowed_attrs : { href : true
                            }
          }
    };

var dax_scrub_tree = {};
var dax_scrub_this_node = dax_scrub_tree;
var dax_scrub_final_html = '';
function dax_scrub(html, scrub_config) {
    // Handle Options
    if ( ! scrub_config ) scrub_config = {};
    if ( ! scrub_config.allowed_tags )    scrub_config.allowed_tags = {};
    if ( ! scrub_config.allowed_attrs )   scrub_config.allowed_attrs = {};
    if ( ! scrub_config.allowed_styles )  scrub_config.allowed_styles = {};
    if ( ! scrub_config.options )         scrub_config.options = {};
    //  Convert Array config items into objects
    for (var i in scrub_config) {
        if (scrub_config[i] instanceof Array || typeof scrub_config[i] == 'array') {
            var newobj = {};
            for (var ii = 0;ii < scrub_config[i].length;ii++) { newobj[scrub_config[i][ii]] = true; }
            scrub_config[i] = newobj;
        }
    }
    // Shortcut
    if ( scrub_config.options.the_works ) {
        scrub_config.options.collapse_tables_nicely = true;
        scrub_config.options.honor_td_brs_like_rows = true;
        scrub_config.options.max_consec_blank_lines = 1;
        scrub_config.options.cull_empty_inline_tags = true;
        scrub_config.options.transform_urls_into_anchors = true;
        scrub_config.options.transform_emails_into_anchors = true;
        scrub_config.options.remove_useless_parents = true;
    }


    /////////////////////////
    ///  First Step, Cull some tags and data with Regex to get it out of the way

    html = html.replace(/<\!--[\s\S]*?-->/ig,'');                          // HTML Comments
    html = html.replace(/<\?[\s\S]*?\?>/ig,'');                            // PHP Tag (REALLY Simple Syntax, not secure...)
    html = html.replace(/<\%[\s\S]*?\%>/ig,'');                            // ASP Tag (REALLY Simple Syntax, not secure...)

////  WOW!  This causes FF and Chrome at least to go into an infinite spiral of lockup even tho this regex is nearly identical to these others...
//    html = html.replace(    /<\!([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>/,''); // <! ... > Tags
    html = html.replace(    /<\![^>]*>/g,''); // <! ... > Tags ==> Simple but non-Thourough workaround for the bug...

    html = html.replace(/<script([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>[\s\S]*?<\/script([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>/ig,'');
    if ( ! scrub_config.allowed_tags.style )    html = html.replace(/<style([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>[\s\S]*?<\/style([^\'\"\>]*(\'[^\']*\'|\"[^\"]*\")?)*>/ig,'');
    if ( ! scrub_config.allowed_tags.textarea ) html = html.replace(/<textarea(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>([\s\S]*?)<\/textarea(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>/ig, dax_reduce_tag_contents_to_html_entites);
    if ( ! scrub_config.allowed_tags.xmp )      html = html.replace(/<xmp(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>([\s\S]*?)<\/xmp(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>/ig,           dax_reduce_tag_contents_to_html_entites);
    if ( ! scrub_config.allowed_tags.pre )      html = html.replace(/<pre(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>([\s\S]*?)<\/pre(?:[^\'\"\>]*(?:\'[^\']*\'|\"[^\"]*\")?)*>/ig,           dax_reduce_tag_contents_to_html_entites);

    // FEATURE: Honor TD BR's like Rows
    if ( scrub_config.options.honor_td_brs_like_rows ) {
        html = html.replace( /<tr[^>]*>[^<]*(<(?!\/?tr)[^>]+>[^<]*)*<\/tr>/gi, dax_honor_td_brs_like_rows); 
    }
    // FEATURE: Collapse Tables Nicely
    if ( scrub_config.options.collapse_tables_nicely ) {
        // </th>   => <br>
        // </tr>   => <br>
        // </td>   => space space space
        html = html.replace(/<\/t[hr][^>]*>/ig, "<br>");
        html = html.replace(/<\/td[^>]*>/ig,    "   " );
    }


    /////////////////////////
    ///  Second, Securely Get the list of tags and content

    var first_split = html.split('>');  last_content = first_split.pop();
    var tags = [];
    var current_tag = ['',''];
    for (i = 0;i < first_split.length;i++) {
        //  If This is a close angle with no (legal) open angle preceeding it, then treat it as content
        x = first_split[i].search(/<[a-z\/]/i); if ( x == -1 ) { current_tag[0] += first_split[i] + '>';  continue; }
        current_tag[0] += first_split[i].slice(0,x);
        current_tag[1] += first_split[i].slice(x) + '>';

        //  If we can detect quote pairs ending in an un-closed quote pair, then we need to read the next item and re-try, etc...
        while ( current_tag[1].match(/^<([^\'\"]*(\'[^\']*\'|\"[^\"]*\"))*([^\'\"]*(\'[^\']*|\"[^\"]*))>$/i) && i < first_split.length ) {
            current_tag[1] += first_split[++i] + '>';
        }

        // Finally, add the tag to the queue
        current_tag[2] = i;  tags.push(current_tag);  current_tag = ['',''];
    }
    // Prepare a Final FLAG TO SIGNAL WHEN WE'RE DONE
    tags.push([last_content,'']);


    /////////////////////////
    ///  Third, Crawl, Order and Apply Rules

    dax_scrub_final_html = '';
    dax_scrub_tree = 
        { tagname: 'root',
          is_root_node: true,
          children: []
        };
    var watch_stuff = 
        { consec_newlines: 0
        };
    dax_scrub_this_node = dax_scrub_tree;
    for (var i = 0;i < tags.length;i++) {
        //  Look for, FLAG TO SIGNAL WHEN WE'RE DONE
        if ( tags[i][1] == '' ) { 

            //  Do Final HTML Flatten...
            dax_scrub_this_node = dax_scrub_tree;
            dax_scrub_tree.children.push({pre_tag_content:''});
            dax_scrub_flatten_stuff(watch_stuff);


            // Now, tack on Last possible piece
            dax_scrub_final_html += tags[i][0];
            break;
        }

        var m = tags[i][1].match(/^<\/?(\w+)/);
        //  The remote case, the tag was malformed like: </ > wth no word char, but with a slash
        if (! m) { tags[i+1][0] = tags[i][0] + tags[i+1][0]; continue; } // keep the content, drop the tag
        
        //  SCRUB the tag, attrbutes, styles, etc
        var scrub_result = dax_scrub_tag( m[1].toLowerCase(), tags[i][1], scrub_config );
        //  If the Scrub removed the tag, ignore the tag, and move on...
        if ( ! scrub_result[0] ) { tags[i+1][0] = tags[i][0] + tags[i+1][0]; continue; } // keep the content, drop the tag
        // Otherwise, continue building our own fake DOM
        var node = 
            { tagname: m[1].toLowerCase(),
              pre_tag_content: dax_scrub_content(tags[i][0], scrub_config),
              tag: scrub_result[1],
              pre_close_tag_content: '',
              close_tag: '',
              flat: scrub_result[2],
              children: []
            };
        
        // FEATURE: Maximum Consecutive Blank Lines : Reset whenever we see content
        if ( ! node.pre_tag_content.match(/^\s*(&nbsp;\s*)*$/) )
            watch_stuff.consec_newlines = 0;

        // OPEN Tag
        if ( tags[i][1].charAt(1) != '/' ) {
            node.parent = dax_scrub_this_node;
            dax_scrub_this_node.children.push( node );

            //  Go INTO this tag, Unless this is "neverNested" or is Self-Closed
            if ( ! dax_neverNested[ node.tagname ] // Like <br>, etc
                 && tags[i][1].charAt( tags[i][1].length - 2 ) != '/'
               ) {
                dax_scrub_this_node = node;
            }
            // Self-closing tag...
            else { 
//                console.log('SELF CLOSING TAG: "'+ node.tag +'" ('+ scrub_config.options.cull_empty_inline_tags +') ('+ dax_inlineElements[ node.tagname ] +'), pre-content: "'+ node.pre_tag_content.substr(0,25) +'..."');
                // FEATURE: Cull Empty Inline Tags
                if ( scrub_config.options.cull_empty_inline_tags
                     && dax_inlineElements[ node.tagname ]                    // If it is an inline element
                   ) {
//                    console.log('Culling Empty Inline ('+ node.tagname +') tag, preceeded by: "'+ node.pre_tag_content.substr(0,25) +'..."');
                    dax_scrub_this_node.children.pop(); // Just pop the child and forget it...
                    tags[i+1][0] = node.pre_tag_content + tags[i+1][0]; continue; // keep the content, drop the tag
                }

                // FEATURE: Maximum Consecutive Blank Lines
                if ( scrub_config.options.max_consec_blank_lines
                     && ( node.tagname == 'br' || node.tagname == 'p' )
                   ) watch_stuff.consec_newlines++;
                if ( watch_stuff.consec_newlines > ( scrub_config.options.max_consec_blank_lines + 1 ) ) {
                    dax_scrub_this_node.children.pop();
                    watch_stuff.consec_newlines--;
                }

                // Flatten DOM to HTML string as we go to save memory (On each close tag)
                dax_scrub_flatten_stuff(watch_stuff);
            }
        }
        // CLOSE Tag
        else {
            // Which Tag does this Close?  Loop back 'til we find it (because it might NOT be THIS one...)
            var test_parent = dax_scrub_this_node;
            while ( test_parent.tagname != node.tagname && ! test_parent.is_root_node ) { test_parent = test_parent.parent; }
            //  If we have a CLOSE, that didn't match an OPEN, ignore the tag
            if ( test_parent.is_root_node ) { tags[i+1][0] = tags[i][0] + tags[i+1][0]; continue; } // keep the content, drop the tag

            //  Hook for Stylesheet Scrubbing
            if ( node.tagname == 'style' ) node.pre_tag_content = dax_scrub_stylesheet( node.pre_tag_content, scrub_config);

            //  Record the Close Tag, Switch to the new Parent
            test_parent.pre_close_tag_content = node.pre_tag_content;
            test_parent.close_tag = node.tag;
            dax_scrub_this_node = test_parent.parent;

            // FEATURE: Remove Useless Parents
            if ( scrub_config.options.remove_useless_parents
                 && test_parent.flat                                                   //  If this parent is flat (not adding style or attributes)
                 && test_parent.children.length == 1                                   //  And it only has one child
                 && test_parent.children[0].pre_tag_content.match(/^\s*(&nbsp;\s*)*$/) //  The content inside these 2 tags ...
                 && test_parent.pre_close_tag_content.match(      /^\s*(&nbsp;\s*)*$/) //  ...  is all whitespace
                 && ( test_parent.children[0].tagname == test_parent.tagname           //  And it's child is the same tag as itself
                      || test_parent.tagname == 'span'                                 //   OR, it's a style-less inline SPAN tag
                      || test_parent.tagname == 'a'                                    //   OR, it's a style-less inline A tag
                    )
               ) {
                //  Splice the Generations...
                test_parent.children[0].pre_tag_content = test_parent.pre_tag_content + test_parent.children[0].pre_tag_content;
                test_parent.parent.children[ test_parent.parent.children.length - 1 ] = test_parent.children[0];
                test_parent.children[0].parent = test_parent.parent;
                // Finally, for steps below, pretend that our child IS us
                test_parent = test_parent.children[0];
            }
            else if ( scrub_config.options.remove_useless_parents
                      && test_parent.flat                                               //  If this parent is flat (not adding style or attributes)
                      && test_parent.children.length == 0                               //  And there are NO children
                      && ! test_parent.pre_close_tag_content.match(/^\s*(&nbsp;\s*)*$/) //  BUT, there IS CONTENT inside the tag
                      && ( test_parent.tagname == 'span'                                //  AND it's a style-less inline SPAN tag
                           || test_parent.tagname == 'a'                                //   OR, it's a style-less inline A tag
                         )
                    ) {
                dax_scrub_this_node.children.pop(); // Drop the whole open and close tag together
//                console.log('Removing Useless parent ('+ test_parent.tagname +') tag, pre-content: "'+ test_parent.pre_tag_content.substr(0,25) +'...", containing: "'+ test_parent.pre_close_tag_content.substr(0,25) +'..."'
//                            + ' AND tacking both these contents before the pre-content of this next tag: "'+ tags[i+1][1] +'"'
//                           );
                tags[i+1][0] = test_parent.pre_tag_content + test_parent.pre_close_tag_content + tags[i+1][0]; continue; // keep the content, drop the tag
            }

            // FEATURE: Cull Empty Inline Tags
            if ( scrub_config.options.cull_empty_inline_tags
                 && dax_inlineElements[ test_parent.tagname ]                    // If it is an inline element
                 && test_parent.children.length == 0                             //  And it has NO children
                 && test_parent.pre_close_tag_content.match(/^\s*(&nbsp;\s*)*$/) //  and the content is all whitespace
               ) {
                dax_scrub_this_node.children.pop(); // Drop the whole open and close tag together
//                console.log('Culling Empty Inline ('+ test_parent.tagname +') tag, preceeded by: "'+ test_parent.pre_tag_content.substr(0,25) +'..."');
                tags[i+1][0] = test_parent.pre_tag_content + tags[i+1][0]; continue; // keep the content, drop the tag
            }

            // Flatten DOM to HTML string as we go to save memory (On each close tag)
            dax_scrub_flatten_stuff(watch_stuff);

            // FEATURE: Maximum Consecutive Blank Lines
            if ( scrub_config.options.max_consec_blank_lines
                 && test_parent.tagname == 'p'                                                         //  When in a P
                 && test_parent.pre_tag_content.match(      /^\s*(&nbsp;\s*)*$/)                       //  and the content ...
                 && test_parent.pre_close_tag_content.match(/^\s*(&nbsp;\s*)*$/)                       //  ...  is all whitespace
                 && ( test_parent.children.length == 0                                                 //  and there are no children
                      || ( test_parent.children[0].pre_tag_content.match(         /^\s*(&nbsp;\s*)*$/) //  OR, at least the children  ...
                           && test_parent.children[0].pre_close_tag_content.match(/^\s*(&nbsp;\s*)*$/) //  ...  have no content either
                         )
                    )
               ) watch_stuff.consec_newlines++; 
            if ( watch_stuff.consec_newlines > ( scrub_config.options.max_consec_blank_lines + 1 ) ) {
                dax_scrub_this_node.children.pop();
                watch_stuff.consec_newlines--;
            }
        }
    }

    return dax_scrub_final_html;
}

function dax_reduce_tag_contents_to_html_entites(content) {
    return content.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/&/g,'&amp;');
}

function dax_scrub_flatten_stuff(watch_stuff) {
    //  Merge all Children into the last child
    for (var i = 0;i < dax_scrub_this_node.children.length;i++) {

        // Merge in it's children
        if ( dax_scrub_this_node.children[i].children 
             && dax_scrub_this_node.children[i].children.length > 0 
           ) {
            var dax_scrub_this_node_BAK = dax_scrub_this_node;
            dax_scrub_this_node = dax_scrub_this_node.children[i];
            dax_scrub_flatten_stuff(watch_stuff);
            dax_scrub_this_node = dax_scrub_this_node_BAK;

            var last_child = dax_scrub_this_node.children[i].children[ dax_scrub_this_node.children[i].children.length - 1 ]

            //  Merge the last child into our pre_close_tag_content
            dax_scrub_this_node.children[i].pre_close_tag_content =
                (   last_child.pre_tag_content
                  + last_child.tag
                  + last_child.pre_close_tag_content
                  + last_child.close_tag
                  + dax_scrub_this_node.children[i].pre_close_tag_content
                );
            dax_scrub_this_node.children[i].children = []; // Then, kill the children
        }

        // Merge this tag into the NEXT SIBLING, unless this is the last sibling
        if ( i < (dax_scrub_this_node.children.length - 1) ) {
            dax_scrub_this_node.children[i+1].pre_tag_content = 
                (   dax_scrub_this_node.children[i].pre_tag_content
                    + dax_scrub_this_node.children[i].tag
                    + dax_scrub_this_node.children[i].pre_close_tag_content
                    + dax_scrub_this_node.children[i].close_tag
                    + dax_scrub_this_node.children[i+1].pre_tag_content
                );
        }
    }

    // Then, kill the ones we just flattened into the last child
    if ( dax_scrub_this_node.children.length > 1 ) dax_scrub_this_node.children.splice(0,dax_scrub_this_node.children.length - 1);
    // If this is the root node, pass this content into the final HTML
    if ( dax_scrub_this_node.is_root_node ) {
        dax_scrub_final_html += dax_scrub_this_node.children[0].pre_tag_content;
        dax_scrub_this_node.children[0].pre_tag_content = '';
    }
}

function dax_scrub_tag(tagname, tag, scrub_config) {
    if ( scrub_config.allowed_tags[ tagname ] ) {
        if ( typeof scrub_config.allowed_tags[ tagname ] == 'boolean' ) scrub_config.allowed_tags[ tagname ] = dax_freeElementAttrs[ tagname ] || {};
        if ( ! scrub_config.allowed_tags[ tagname ].allowed_attrs )     scrub_config.allowed_tags[ tagname ].allowed_attrs  = {};
        if ( ! scrub_config.allowed_tags[ tagname ].allowed_styles )    scrub_config.allowed_tags[ tagname ].allowed_styles = {};
        if ( ! scrub_config.allowed_tags[ tagname ].disallowed_attrs )  scrub_config.allowed_tags[ tagname ].disallowed_attrs  = {};
        if ( ! scrub_config.allowed_tags[ tagname ].disallowed_styles ) scrub_config.allowed_tags[ tagname ].disallowed_styles = {};

        //  Implied Style attr allowed unless globally disabled
        if ( ! scrub_config.options.disable_style_attr &&
             scrub_config.allowed_tags[ tagname ].allowed_attrs.style === undefined
           ) scrub_config.allowed_tags[ tagname ].allowed_attrs.style = true;

        //  Scrub the tag's attributes...                                                                                                                                                 
        var keepAttrs = [];
        var close_tag = ( tag.charAt( tag.length - 2) == '/' || dax_neverNested[ tagname ] ) ? '/>' : '>';
        var open_tag = ( ( tag.charAt(1) == '/') ? '</' : '<') + tagname;
        var attrs = tag.substr(open_tag.length, tag.length - open_tag.length - close_tag.length).match(/(\w+)\=(\"[^\"]*\"|\'[^\']*\'|[^\"\s]+)/g);
        if (! attrs) return [true,(open_tag + close_tag),true];

        var styleAttrRules = [];
        // Loop thru the attributes
        for (var i = 0;i < attrs.length;i++) {
            var n_v = attrs[i].match(/^([\w-]+)=\"?([^\"]+)\"?$/) || attrs[i].match(/^([\w-]+)=\'?([^\']+)\'?$/);
            if ( ! n_v ) continue;
            attr = n_v[1].toLowerCase();

            // Attribute Rules : Simple Allow / Disallow
            if ( ( ! scrub_config.allowed_attrs[ attr ] && ! scrub_config.allowed_tags[ tagname ].allowed_attrs[ attr ] )
                 || scrub_config.allowed_tags[ tagname ].disallowed_attrs[ attr ]
               ) continue;
            // Attribute Rules : Tag Inspecific Attr Value regexp
            if ( typeof scrub_config.allowed_attrs[ attr ] == 'object'
                 && ( ( scrub_config.allowed_attrs[ attr ].regexp
                        && ! n_v[2].match( scrub_config.allowed_attrs[ attr ].regexp )
                      )
                      || ( scrub_config.allowed_attrs[ attr ].neg_regexp
                           && n_v[2].match( scrub_config.allowed_attrs[ attr ].neg_regexp )
                         )
                    )
               ) continue;
            // Attribute Rules : Tag Specific Attr Value regexp
            if ( typeof scrub_config.allowed_tags[ tagname ].allowed_attrs[ attr ] == 'object'
                 && ( ( scrub_config.allowed_tags[ tagname ].allowed_attrs[ attr ].regexp
                        && ! n_v[2].match( scrub_config.allowed_tags[ tagname ].allowed_attrs[ attr ].regexp )
                      )
                      || ( scrub_config.allowed_tags[ tagname ].allowed_attrs[ attr ].neg_regexp
                           && n_v[2].match( scrub_config.allowed_tags[ tagname ].allowed_attrs[ attr ].neg_regexp )
                         )
                    )
               ) continue;
            
            //  Parse CSS Styles for Style Rules
            if ( attr == 'style' ) {
                var rules = n_v[2].split(/\s*;\s*/);
                // Loop thru STYLE rules                                                                                                                                              
                for (var ii = 0;ii < rules.length;ii++) {
                    var style_n_v = rules[ii].match(/^([\w-]+)\s*:\s*([\s\S]+)?$/);
                    if ( ! style_n_v || typeof style_n_v[2] == 'undefined' ) continue;
                    style = style_n_v[1].toLowerCase();

                    // Style Rules : Simple Allow / Disallow
                    if ( ( ! scrub_config.allowed_styles[ style ] && ! scrub_config.allowed_tags[ tagname ].allowed_styles[ style ] )
                         || scrub_config.allowed_tags[ tagname ].disallowed_styles[ style ]
                       ) continue;
                    // Style Rules : Tag Inspecific Style Value regexp
                    if ( typeof scrub_config.allowed_styles[ style ] == 'object'
                         && ( ( scrub_config.allowed_styles[ style ].regexp
                                && ! style_n_v[2].match( scrub_config.allowed_styles[ style ].regexp )
                              )
                              || ( scrub_config.allowed_styles[ style ].neg_regexp
                                   && style_n_v[2].match( scrub_config.allowed_styles[ style ].neg_regexp )
                                 )
                            )
                       ) continue;
                    // Style Rules : Tag Specific Style Value regexp
                    if ( typeof scrub_config.allowed_tags[ tagname ].allowed_styles[ style ] == 'object'
                         && ( ( scrub_config.allowed_tags[ tagname ].allowed_styles[ style ].regexp
                                && ! style_n_v[2].match( scrub_config.allowed_tags[ tagname ].allowed_styles[ style ].regexp )
                              )
                              || ( scrub_config.allowed_tags[ tagname ].allowed_styles[ style ].neg_regexp
                                   && style_n_v[2].match( scrub_config.allowed_tags[ tagname ].allowed_styles[ style ].neg_regexp )
                                 )
                            )
                       ) continue;

                    styleAttrRules.push(rules[ii]);
                }
            }
            else {
                keepAttrs.push(attrs[i]);
            }
        }
        if ( styleAttrRules.length > 0 ) keepAttrs.push('style="'+ styleAttrRules.join('; ') +'"');

        if ( keepAttrs.length > 0 ) return [true,(open_tag +' '+ keepAttrs.join(' ') + close_tag),false];
        return [true,(open_tag + close_tag),true];
    }
    else {
        return [false,'',true];
    }
}

function dax_scrub_stylesheet(content, scrub_config) {
    if ( ! scrub_config.allowed_tags.style.allowed_styles )    scrub_config.allowed_tags.style.allowed_styles = {};
    if ( ! scrub_config.allowed_tags.style.disallowed_attrs )  scrub_config.allowed_tags.style.disallowed_attrs  = {};
    if ( ! scrub_config.allowed_tags.style.disallowed_styles ) scrub_config.allowed_tags.style.disallowed_styles = {};

    return content.replace(/\{([^\}]+)\}/g, function (x, style_str) {

        styleAttrRules = [];
        var rules = style_str.split(/\s*;\s*/);
        // Loop thru STYLE rules
        for (var ii = 0;ii < rules.length;ii++) {
            var style_n_v = rules[ii].match(/^\s*([\w-]+)\s*:\s*([\s\S]+?)?$/);
            if ( ! style_n_v || typeof style_n_v[2] == 'undefined' ) continue;
            style = style_n_v[1].toLowerCase();
            
            // Style Rules : Simple Allow / Disallow
            if ( ( ! scrub_config.allowed_styles[ style ] && ! scrub_config.allowed_tags.style.allowed_styles[ style ] )
                 || scrub_config.allowed_tags.style.disallowed_styles[ style ]
               ) continue;
            // Style Rules : Tag Inspecific Style Value regexp
            if ( typeof scrub_config.allowed_styles[ style ] == 'object'
                 && ( ( scrub_config.allowed_styles[ style ].regexp
                        && ! style_n_v[2].match( scrub_config.allowed_styles[ style ].regexp )
                      )
                      || ( scrub_config.allowed_styles[ style ].neg_regexp
                           && style_n_v[2].match( scrub_config.allowed_styles[ style ].neg_regexp )
                         )
                    )
               ) continue;
            // Style Rules : Tag Specific Style Value regexp
            if ( typeof scrub_config.allowed_tags.style.allowed_styles[ style ] == 'object'
                 && ( ( scrub_config.allowed_tags.style.allowed_styles[ style ].regexp
                        && ! style_n_v[2].match( scrub_config.allowed_tags.style.allowed_styles[ style ].regexp )
                      )
                      || ( scrub_config.allowed_tags.style.allowed_styles[ style ].neg_regexp
                           && style_n_v[2].match( scrub_config.allowed_tags.style.allowed_styles[ style ].neg_regexp )
                         )
                    )
               ) continue;
            
            styleAttrRules.push(rules[ii]);
        }
        return '{' + styleAttrRules.join('; ') +'}';
    });
}

function dax_scrub_content(content, scrub_config) {

    // FEATURE: Convert URL's into links
    //  Regex Explanation: We need to match at least one Non-URL-ish char
    //    after the email before the negative lookahead assertions, otherwise,
    //    the pattern will simply match one less char of the URL.
    //    So, the 1-char after the email forces the [^\s\"<>]+ to be greedy...
    // Also: The optional prefixes (e.g. [^<]*?) in the negative lookahead 
    //   are because there may be other attributes in the <a> tag, or spaces
    //   between the URL and </a> tag...
    if ( scrub_config.options.transform_urls_into_anchors
         && content.indexOf('://') != -1
        ) content = content.replace(/((https?|ftp)\:\/\/[\w\:\?\!\%\=\-\.\/]+)(([^\"\w\:\?\!\%\=\-\.\/])(?!([^<]*?<)?\/a)|$)/ig,'<a href="$1">$1</a>$3');

    // FEATURE: Convert email's into links
    //   Regex Explanation: We need to match at least one Non-Email-ish char
    //     after the email before the negative lookahead assertions, otherwise,
    //     the pattern will simply match one less char of the email address.
    //     So, the 1-char after the email forces the [^\s\"<>]+ to be greedy...
    //   Also: The optional prefixes (e.g. [^<]*?) in the negative lookahead 
    //     are because there may be other attributes in the <a> tag, or spaces
    //     between the email and </a> tag...
    if ( scrub_config.options.transform_emails_into_anchors
         && content.indexOf('@') != -1
       ) content = content.replace(/([^\s\&\;\"<>]+\@[^\s\&\;\"<>]+)(\"(?![^<]*?>)|[^\"\w\.\-\+](?!([^<]*?<)?\/a)|$)/g,'<a href="mailto:$1">$1</a>$2');

    return content;
}


//  converting breaks in tables into individual rows
//  <tr><td>($1a)<br/>($2a)...<br/>($na)</td><td>($1b)<br/>($2b)...<br/>($nb)</td></tr>
//  => <tr><td>($1a)</td><td>($1b)</td></tr>...<tr><td>($na)</td><td>($nb)</td></tr>
function dax_honor_td_brs_like_rows(row) {
    var new_rows = [];
    // drill down into each td
    var td_all = row.match(/<td[^>]*>[^<]*(?:<(?!\/td)[^>]*>[^<]*)*<\/td>/gi);
    if ( ! td_all ) return '';
    for (var i = 0;i < td_all.length;i++) {
        // get all the separated lines
        td = strip_outer_tags(td_all[i]);
        var lines = td.split(/\s*<br[^>]*>\s*/);
        for (var ii = 0;ii < lines.length;ii++) {
            new_rows[ii] = new_rows[ii] || [];
            new_rows[ii][i] = lines[ii];
        }
    }
    var ret_str = "";
    for (var i = 0;i < new_rows.length;i++) {
        ret_str = ret_str +"<tr><td>" + new_rows[i].join("</td><td>") + "</td></tr>";
    }
    return ret_str;
}

function strip_outer_tags(input) {
    input = input.replace(/^<[^>]*>/, "" );
    input = input.replace(/<[^>]*>$/, "" );
    return input;
}
