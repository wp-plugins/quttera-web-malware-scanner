<?php
/*
Plugin Name:        Quttera Web Malware Scanner
Plugin URI:         http://quttera.com
Description:        Web malware and vulnerability exploits scanner for WordPress.
Version:            2.0.7
Author:             Quttera team
Author URI:         http://quttera.com/
License:            GNU General Public License v2
*/

if(!function_exists('add_action'))
{
    exit(0);
}

define('QUTTERA_WM_SCANNER','quttera_wm_scanner');
define('QUTTERA_WM_SCANNER_VERSION','2.0.7');
define('QUTTERA_URL',plugin_dir_url( __FILE__ ));

add_action( 'admin_enqueue_scripts', 'quttera_style', 1 );
function quttera_style() 
{
    if(isset($_GET['page']) && is_string($_GET['page']) && preg_match('/^quttera_wm_scanner$/',$_GET['page']) ) 
    {
        echo '<link rel="stylesheet" href="'.QUTTERA_URL.'quttera_css.css" type="text/css" media="all" />';
    }
}


function debug( $msg )
{
    if(!isset($GLOBALS['DebugMyPlugin'])){ return;}
    $GLOBALS['DebugMyPlugin']->panels['main']->addPR('header','Big Array is now:',__FILE__,__LINE__);
}

/* qtr_wm_scanner */
@ini_set( 'max_execution_time', 300 );

/**
 * Set up the menu item and register with hooks to print JS and help.
 */
function qtr_setup_scanner_menu() {
    debug( "qtr_setup_scanner_menu called");
    $page_hook = add_menu_page( 'Quttera Web Malware Scanner', 
                                'Quttera Scanner',
                                'manage_options',
                                'quttera_wm_scanner', 
                                'qtr_admin_page'
                            ); 
    
    if ( $page_hook ) 
    {
        add_action( "admin_print_styles-$page_hook", 'add_thickbox' );
        add_action( "admin_footer-$page_hook", 'qtr_load_admin_scripts' );
    }
}

add_action( 'admin_menu', 'qtr_setup_scanner_menu' );


/**
 * Print scripts that power paged scanning and diff modal.
 */
function qtr_load_admin_scripts() { ?>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#run-scanner').click( function() {
            var url             = $('#url_name').val();
            var qtr_srv_name     = $('#qtr_srv_name').val();
            
            $.ajaxSetup({
                type: 'POST',
                url: ajaxurl,
                complete: function(xhr,status) {
                    if ( status != 'success' ) {
                        //alert("Failed to communicate with WP");
                    }
                }
            });
            run_scan(url,qtr_srv_name);
            $('#run-scanner').hide();
            return false;
        });
    }); 

    /*
     * URL validation procedure
     */
    function validateURL(textval) {
         var urlregex = new RegExp(
            "^(http|https|ftp)\://([a-zA-Z0-9\.\-]+(\:[a-zA-Z0-9\.&amp;%\$\-]+)*@)*((25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[1-9])\.(25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[1-9]|0)\.(25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[1-9]|0)\.(25[0-5]|2[0-4][0-9]|[0-1]{1}[0-9]{2}|[1-9]{1}[0-9]{1}|[0-9])|([a-zA-Z0-9\-]+\.)*[a-zA-Z0-9\-]+\.(com|edu|gov|int|mil|net|org|biz|arpa|info|name|pro|aero|coop|museum|[a-zA-Z]{2,12}))(\:[0-9]+)*(/($|[a-zA-Z0-9\.\,\?\'\\\+&amp;%\$#\=~_\-]+))*$");
         return urlregex.test(textval);
    }

    /*
     * Domain validation procedure
     */
    function validateDomain(domain) { 
        //var re = new RegExp(/^[a-zA-Z0-9][a-zA-Z0-9-_]{0,61}[a-zA-Z0-9]{0,1}\.([a-zA-Z]{1,6}|[a-zA-Z0-9-]{1,30}\.[a-zA-Z]{2,10})$/); 
        var d = domain.trim();
        var re = new RegExp(/^(www\.)?([a-zA-Z0-9][a-zA-Z0-9-_]{0,45}[a-zA-Z0-9]\.)+[a-zA-Z]{2,10}$/);
        return d.match(re);
    }   


    run_scan = function(this_url,qtr_url,level) {
        if( !validateDomain(this_url) ){
	        //alert(this_url);
	        hide_all();
            var curr_time = new Date().getTime();
            show_investigation_status( {    "state" : "Provided name of this web-site is invalid", 
                                            "age"   : curr_time,
                                            "url"   : "<invalid>"
                                       });
	        return;
	    }

        if( !validateURL(qtr_url) ){
            //alert(qtr_url);
    	    hide_all();
            var curr_time = new Date().getTime();
            show_investigation_status({ "state" : "Provided name of Qutter web malware scanner is invalid", 
                                        "age"   : curr_time,
                                        "url"   : "<invalid>" });
    	    return;
	    }

        if( !level )
        {
            hide_all();
            var curr_time = new Date().getTime();
            show_investigation_status( {    "state" : "starting", 
                                            "age"   : curr_time,
                                            "url"   : this_url });
        }

        jQuery.ajax({
            data: {
                action: 'scanner-run_scan',
                _this: this_url,
                _qtr_url: qtr_url
                //_nonce: qtr_wm_scanner_nonce
            }, 
            success: function(r) {
                var res = jQuery.parseJSON(r);
                var state = res.content.state.toLowerCase();
                //alert(res);
                //alert(res.status);
                //alert("Content: " + res.content);
                //alert("State: " + res.content.state );
                
                if ( state == 'new' )
                {
                    show_investigation_status({ "state" : "Waiting for free web malware scanner slot.",
                                                "age"   : res.content.age,
                                                "url"   : res.content.url,
                                                "priority" : res.content.priority });

                    run_scan(this_url,qtr_url,1); //recursive call
                }
                else if( state == 'download')
                {
                    show_investigation_status({ "state" : "Website content is being downloaded for investigation.", 
                                                "age"   : res.content.age,
                                                "url"   : res.content.url,
                                                "priority" : res.content.priority,
                                                "processed_files": res.content.processed_files  });

                    run_scan(this_url,qtr_url,1); //recursive call
                }
                else if( state =='downloaded' )
                {
                    show_investigation_status({ "state" : "Website content has been downloaded and is waiting for scanner.", 
                                                "age"   : res.content.age,
                                                "url"   : res.content.url,
                                                "priority" : res.content.priority });

                    run_scan(this_url,qtr_url,1); //recursive call
                }
                else if( state =='scan' || state =='scanned' )
                {
                    show_investigation_status({ "state" : "website content is being scanned", 
                                                "age"   : res.content.age,
                                                "url"   : res.content.url,
                                                "priority" : res.content.priority,
                                                "processed_files": res.content.processed_files });

                    run_scan(this_url,qtr_url,1); //recursive call
                }
                else if( state == 'clean' )
                {
                    show_investigation_report(res.content);
                }
                else if( state=='potentially suspicious' || state=='potentially unsafe' )
                {
                    show_investigation_report(res.content);
                }
                else if (state=='suspicious' || state=='unsafe')
                {
                    show_investigation_report(res.content);                
                }
                else if (state=='malicious')
                {
                    show_investigation_report(res.content);                
                }
                else
                {
                    show_investigation_error(res.content);
                }                
            }//end of success function
        });
    };

    show_investigation_error = function( status ){
        var urlDate     = new Date();
        var currentdate = urlDate.toLocaleString();

        jQuery('#investigation_error').empty();
            var str =   "State: "   + status.state  + "</br>" +
                        "Time: "    + currentdate   + "</br>" +
                        "Url: "     + status.url;
        //alert("Status: " + str );
        hide_all();
        jQuery('#investigation_error').append("<p>" + str + "</p>");
        jQuery('#investigation_error').show();
        jQuery('#run-scanner').show();
    }
   
    /*
     * status comprised from fields:
     *      url  
     *      priority
     *      state  
     *      age   
     *      processed_files
     */
  
    show_investigation_status = function ( status ){
        hide_all();
        var urlDate     = new Date();
        var currentdate = urlDate.toLocaleString();

        jQuery('#investigation_progress').empty();
        var str = "State: <b> " + status.state  + "</b></br>" +
                  "Time: "  + currentdate   + "</br>" +
                  "Url: "   + status.url    + "</br>";

        if( status.priority )
        {
            str += "Investigation priority: " + status.priority + "</br>";
        }

        if( status.processed_files )
        {
            str += "Processed files: " + status.processed_files + "</br>";
        }
        
        jQuery('#investigation_progress').append("<p>" + str + "</p>");
        jQuery('#investigation_progress').show();
    };
   
 
    show_investigation_report = function ( scan_report ){
        hide_all();
        jQuery('#investigation_result').empty();
        jQuery('#investigation_result').append('<H2>Website Malware Investigation Report</H2><hr>');
        jQuery('#investigation_result').append('<a href="http://quttera.com/article/about-quttera-malware-scan-report" target="_blank">Understanding security reports</a>');
                
        var clean_files             = 0;
        var pot_suspicious_files    = 0;
        var suspicious_files        = 0;
        var malicious_files         = 0;
        
        for( var i = 0; i < scan_report.report.length; i ++ )
        {
            var threat = scan_report.report[i].threat.toLowerCase();
            if( threat == "malicious" ){
                malicious_files         += 1;
            }else if( threat == "suspicious" ){
                suspicious_files        +=1;
            }else if( threat == "potentially suspicious"){
                pot_suspicious_files    += 1;
            }else{
                clean_files             += 1;
            }
        }

        var summary =   "<table>" +
                        "<tr><td align='left'><b>Server IP:</b></td>" +
                             "<td align='left'><b>" + scan_report.ipaddr + "</b></td></tr>" +

                        "<tr><td align='left'><b>Location:</b></td>" +
                             "<td align='left'><b>" + scan_report.country + "</b></td></tr>" +

                        "<tr><td align='left'><b>Web Server:</b></td>" +
                             "<td align='left'><b>" + scan_report.http_server + "</b></td></tr>" +

                        "<tr><td align='left'><font color='green'><b>Clean files: </b></font></td>"+
                            "<td align='left'><font color='green'><b>"  + clean_files + "</b></font></td></tr>" +

                        "<tr><td align='left'><font color='orange'><b>Potentially Suspicious files: </b></font></td>"+
                            "<td align='left'><font color='orange'><b>" + pot_suspicious_files + "</b></font></td></tr>" +

                        "<tr><td align='left'><font color='red'><b>Suspicious files: </b></font></td>"+
                            "<td align='left'><font color='red'><b>" + suspicious_files + "</b></font></td></tr>" +

                        "<tr><td align='left'><font color='#780000'><b>Malicious files: </b></font></td>" +
                            "<td align='left'><font color='#780000'><b>"    + malicious_files + "</b></font></td></tr>";


        if(  scan_report.is_blacklisted )
        {
            if(  scan_report.is_blacklisted &&  scan_report.is_blacklisted.toLowerCase() == "no" )
            {
                summary +=  "<tr><td align='left'><font color='green'><b>Blacklisted: </b></font></td>"+
                                    "<td align='left'><font color='green'><b>"  + scan_report.is_blacklisted + "</b></font></td></tr>";
            }
            else
            {
                summary +=  "<tr><td align='left'><font color='red'><b>Blacklisted: </b></font></td>"+
                                    "<td align='left'><font color='red'><b>"  + scan_report.is_blacklisted + "</b></font></td></tr>";
            }
        }

        summary +=      "<tr><td align='left'><b>External links:</b></td>" +
                             "<td align='left'><b>" + scan_report.links_count + "</b></td></tr>" +

                        "<tr><td align='left'><b>Detected iframes:</b></td>" +
                             "<td align='left'><b>" + scan_report.iframes_count + "</b></td></tr>" +

                        "<tr><td align='left'><b>External domains:</b></td>" +
                             "<td align='left'><b>" + scan_report.domains_count + "</b></td></tr>" +
 
                        "</table>" + 
                        "<hr/>";

        jQuery('#investigation_result').append(summary);
        
        var scanner_server = document.getElementById('qtr_srv_name').value;
        var domain_name    = document.getElementById('url_name').value;
        var full_url       = scanner_server + "/detailed_report/" + domain_name;
        jQuery('#investigation_result').append("<a href='" + full_url + "' target='_blank' >Full investigation report could be found here</a>");

        jQuery('#investigation_report_info').show();
        jQuery('#run-scanner').show();
        jQuery('#investigation_result').show();       
    };
    
    
    hide_all = function(){
        jQuery('#investigation_result').hide();
        jQuery('#investigation_error').hide();
        jQuery('#investigation_progress').hide();    
        jQuery('#quttera_detected_malicious_content').hide();    
    }


    
</script>
<?php
}


function is_valid_domain_name($domain_name)
{
    return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) //valid chars check
            && preg_match("/^.{1,253}$/", $domain_name) //overall length check
            && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)   ); //length of each label
}

function qtr_wm_scanner_ajax_run_scan()
{
    if(!current_user_can('manage_options'))
    {
        wp_die(__('You do not have sufficient permissions to access this page.') );
    }
	
    //check_ajax_referer( 'qtr_wm_scanner-scan' );
    $this_url = trim($_POST['_this']);        /* domain name of this server */
    $qtr_url  = trim($_POST['_qtr_url']);     /* quttera investigation server name */
     
    if( empty($this_url) )
    {
        $this_url = qtr_get_domain_name();
    }
    else if( empty($qtr_url) )
    {
        $qtr_url = "http://wp.quttera.com";
    }

    if( strpos($this_url, "://" ) != false )
    {
        $parse      = parse_url($this_url);
        $this_url   = $parse['host'];
    }
    /* 
     * validate input of host name 
     */
    if(filter_var(gethostbyname($this_url), FILTER_VALIDATE_IP) === FALSE )
    {
        /* send error to frontend */
	    echo json_encode( array( 'content' => array( "state" => "Failed to access local server",
                                                     "age" => time(),
                                                     "url" => "localhost" )));
        exit;

    }
    
    /* validate quttera server address */
    if( filter_var($qtr_url, FILTER_VALIDATE_URL) === FALSE )
    {
        /* send error to fronend */
    	echo json_encode( array( 'content' => array( "state" => "Failed to access remote server",
                                                     "age" => time(),
                                                     "url" => $this_url )));
        exit;
    }

    $investigation_url =  $qtr_url . "/wp_scan/" . $this_url;

    if( filter_var($investigation_url, FILTER_VALIDATE_URL) === FALSE )
    {
	    echo json_encode( array( 'content' => array( "state" => "Remote server address is invalid",
                                                     "age" => time(),
                                                     "url" => "<undefined>" )));
        exit;
    }

    usleep(1000000); //sleep for a second

    $output = file_get_contents($investigation_url);
    
    if( empty($output) )
    {
        $output = file_get_contents($investigation_url);
    
        if( $output == false )
        {
            echo json_encode( array( 'content' => array( "state" => "Failed to access investigation server [ " . $qtr_url . " ]",
                                                         "age" => time(),
                                                         "url" => $this_url,
                                                         "img" => plugins_url( 'loader.gif', __FILE__ )) 
                                    ) 
                            );
            exit;
        }
    }
    
    $output = json_decode($output);
    
    //if state is not finished sleep for a second
    echo json_encode( array( 'content' => $output ) );
    exit;
}

add_action( 'wp_ajax_scanner-run_scan', 'qtr_wm_scanner_ajax_run_scan' );

/**
 * add_management_page callback
 */
function qtr_admin_page(){
    // non-ajax scan form processing
    echo '<table>';
    echo '<tr>';
    echo '<td><img src="' . plugins_url( 'quttera_icon.png', __FILE__ ) . '" height="30px" width="30px"/></td>';
    echo '<td><h2>Quttera Web Malware Scanner for WordPress (Plugin version 2.0.7)</h2></td>';
    echo '</tr>';
    echo '</table>';
    
    if(!current_user_can('manage_options'))
    {
        wp_die(__('You do not have sufficient permissions to access this page.') );
    }

    show_admin_page();
}


function qtr_get_domain_name(){
    $domain_name = network_site_url( '/' );
    $parse = parse_url($domain_name);
    return $parse['host']; // prints 'google.com'
}


/**
 * Display scan initiation form and any stored results.
 */
function show_admin_page() {
    global $wp_version;
    delete_transient( 'exploitscanner_results_trans' );
    delete_transient( 'exploitscanner_files' );
    $results = get_option( 'exploitscanner_results' );
?>
    <div style="float:left;">
        <form action="<?php admin_url( 'tools.php?page=quttera_wm_scanner' ); ?>" method="post">
            <?php wp_nonce_field( /*action*/'qtr_wm_scanner-scan_url' ); ?>
            <input type="hidden" name="action" value="scan" />
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="url_name">Your domain name:</label>
                    </th>
                    <td>
                        <input type="text" size="40" id="url_name" name="url_name" value="<?php echo qtr_get_domain_name(); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="url_name">Quttera Scanner:</label></th>
                    <td>
                        <input type="text" size="40" id="qtr_srv_name" name="qtr_srv_name" value="http://wp.quttera.com"/>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" id="run-scanner" class="button-primary" value="Scan my website"/>
            </p>
        </form>
    </div>

    <div id="help_block" style="margin-top:-49px;margin-right:10px;float:right;width:auto;padding:10px;background:#ffffff;border:1px solid #c6c6c6; border-radius:5px; ">
         <p><a href="http://quttera.com/about" target="_blank">About us</a></p>
         <p><a href="mailto:contactus@quttera.com?Subject=Wordpress%20user%20request" target="_top">Contact us</a></p>
         <p><a href="http://helpdesk.quttera.com" target="_blank">Contact support team</a></p>
         <p><a href="http://quttera.com/faq" target="_blank">FAQ section</a></p>
         <p><a href="http://quttera.com/article/about-quttera-malware-scan-report" target="_blank">About Quttera investigation reports</a></p>
         <p><a href="http://quttera.com/article/how-to-fix-redirect" target="_blank">How to fix website redirection</a></p>
         <p><a href="http://quttera.com/resources/Investigation_engine_added_value_and_user_benefits.pdf" target="_blank">About Quttera investigation technology</a></p>
         <p><a href="http://quttera.blogspot.com/2013/09/my-site-is-blacklisted-what-next.html" target="_blank">My site is blacklisted. What next?</a></p>
         <p><a href="http://quttera.blogspot.com/2013/11/javascript-malware-in-websites.html" target="_blank">What is JavaScript malware</a></p>
         <p><a href="http://blog.quttera.com/2015/01/how-to-locate-hosts-that-infecting-or-spamming-from-your-wordpress-site.html" target="_blank">How to find WordPress reinfection source</a></p>
         <p><a href="http://us4.campaign-archive2.com/?u=90d60a6d22c38baa430812885&id=445f5d2199" target="_blank">News</a></p>
         <p><b>Our services</b></p>
         <form action="http://quttera.com/website-anti-malware-monitoring" target="new"> 
            <input type="submit" class="button-primary" value="Web malware monitoring and alerting" style="font-weight: bold;"/>
         </form>
         <p></p>
         <form action="http://quttera.com/anti-malware-website-monitoring-signup" target="new"> 
            <input type="submit" class="button-primary" value="Malware Cleaning & Monitoring Plans" style="font-weight: bold;"/>
         </form>
    </div>
    <!-- background:#f7f7f7 -->
    <div id="investigation_progress" style="width:600px;float:left;display:none;margin:10px;padding:10px;background:#ffffff;border:1px solid #c6c6c6;text-align:center; border-radius:6px;">
        <p><strong>Scanning your web site for web malware, vulnerability exploits and suspicious signs</strong></p>
        <p><span style="margin-right:5px"></span><img src="<?php echo plugins_url( 'loader.gif', __FILE__ ); ?>" height="16px" width="16px" alt="loading-icon" /></p>
    </div>

    <div id="investigation_error" style="width:600px;float:left;display:none;margin:10px;padding:10px;background:#f7f7f7;border:1px solid #c6c6c6;text-align:center">
        <p><strong>Communication error occured. Try to scan later.</strong></p>
    </div>
    <div id="investigation_result" style="width:600px;float:left;display:none;margin:10px;padding:10px;background:#ffffff;border:1px solid #c6c6c6;border-radius:10px; text-align:left">
    </div>
    <div id="investigation_report_info" style="width:700px;float:left;display:none;">
        <hr> 
        <h3>Question: My website is detected by Quttera as malicious. What do I need to do?</h3>
        <h4>Answer: All our annual plans include malware and blacklist removal. Please just sign-up on
            <a href="http://quttera.com/anti-malware-website-monitoring-signup" target="_blank">quttera.com</a> 
            to any of our annual plans and we will clean your website from malicious content.<h4>
        <hr>
        <h3>Question: My website is incorrectly detected by Quttera. How can I submit a false positive to Quttera?</h3>
        <h4>Answer: Please email us to <b>support@quttera.com</b> about the situation where you believe that Quttera engine or 
            ThreatSign product or Quttera WordPress Plugin is incorrectly reporting a clean/good website as being a threat 
            or malicious in some way. Please indicate a subject as false positive and specify your domain/URL in the email.
        </h4>
    </div>
    <?php
}



/**
 * Activation callback.
 *
 * Add database version info. Set up non-autoloaded options as there is no need
 * to load results or clean core files on any page other than the exploit scanner page.
 */
function on_qtr_scanner_activation() {
    register_uninstall_hook( __FILE__, 'on_qtr_scanner_uninstall' );
}

register_activation_hook( __FILE__, 'on_qtr_scanner_activation' );

/**
 * Deactivation callback. Remove transients.
 */
function on_qtr_scanner_deactivate() {
    /* remove cached (runtime) parameters */
}

register_deactivation_hook( __FILE__, 'on_qtr_scanner_deactivate' );


/**
 * Uninstall callback. Remove all data stored by the plugin.
 */
function on_qtr_scanner_uninstall()
{
    /* removes all added options */
}

/**
 * Update routine to perform database cleanup and to ensure that newly
 * introduced settings and defaults are enforced.
 */
function on_qtr_scanner_admin_init() 
{
    on_qtr_scanner_activation();
}

/* admin_init is triggered before any other hook when a user access the admin area */
add_action( 'admin_init', 'on_qtr_scanner_admin_init' );

/* ?????? */
function qtr_wm_scanner_plugin_actions( $links, $file ) {
     if( $file == 'quttera/quttera_wm_scanner.php' && function_exists( "admin_url" ) ) {
        $settings_link = '<a href="' . admin_url( 'tools.php?page=quttera_wm_scanner' ) . '">' . __('Scanner Settings') . '</a>';
        array_unshift( $links, $settings_link ); // before other links
    }
    return $links;
}

add_filter( 'plugin_action_links', 'qtr_wm_scanner_plugin_actions', 10, 2 );

/* end of file */
