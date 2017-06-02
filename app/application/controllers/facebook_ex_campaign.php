<?php

require_once("home.php"); // loading home controller

class facebook_ex_campaign extends Home
{

    public $user_id;    
    
    public function __construct()
    {
        parent::__construct();
        if ($this->session->userdata('logged_in') != 1)
        redirect('home/login_page', 'location');   
        if($this->session->userdata('user_type') != 'Admin' && !in_array(76,$this->module_access))
        redirect('home/login_page', 'location'); 
        $this->user_id=$this->session->userdata('user_id');

        if($this->session->userdata("facebook_rx_fb_user_info")==0)
        redirect('facebook_rx_account_import/index','refresh');
    
        $this->load->library("fb_rx_login");
        $this->important_feature();
        $this->member_validity();        
    }


    public function index()
    {
      $this->campaign_report();
    }


    public function campaign_report()
    {
        $data['body'] = "facebook_ex/campaign/campaign_list";
        $data['page_title'] = $this->lang->line("Campaign List");
        $page_info = $this->db->query("SELECT page_id,page_name,id FROM `facebook_rx_fb_page_info` WHERE facebook_rx_fb_user_info_id = '".$this->session->userdata("facebook_rx_fb_user_info")."'")->result_array();
        $data['page_info'] = $page_info;
        $this->_viewcontroller($data);
    }

    public function campaign_report_data()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET')
        redirect('home/access_forbidden', 'location');

        $page = isset($_POST['page']) ? intval($_POST['page']) : 15;
        $rows = isset($_POST['rows']) ? intval($_POST['rows']) : 5;
        $sort = isset($_POST['sort']) ? strval($_POST['sort']) : 'id';
        $order = isset($_POST['order']) ? strval($_POST['order']) : 'DESC';

        $campaign_name = trim($this->input->post("campaign_name", true));
        $posting_status = trim($this->input->post("posting_status", true));
        $page_ids = trim($this->input->post("page_ids", true));
        $scheduled_from = trim($this->input->post('scheduled_from', true));        
        if($scheduled_from) $scheduled_from = date('Y-m-d H:i:s', strtotime($scheduled_from));

        $scheduled_to = trim($this->input->post('scheduled_to', true));
        if($scheduled_to) $scheduled_to = date('Y-m-d H:i:s', strtotime($scheduled_to));

        $is_searched = $this->input->post('is_searched', true);


        if($is_searched) 
        {
            $this->session->set_userdata('facebook_ex_conversation_campaign_name', $campaign_name);
            $this->session->set_userdata('facebook_ex_conversation_posting_status', $posting_status);
            $this->session->set_userdata('facebook_ex_conversation_page_ids', $page_ids);
            $this->session->set_userdata('facebook_ex_conversation_scheduled_from', $scheduled_from);
            $this->session->set_userdata('facebook_ex_conversation_scheduled_to', $scheduled_to);
        }

        $search_campaign_name  = $this->session->userdata('facebook_ex_conversation_campaign_name');
        $search_posting_status  = $this->session->userdata('facebook_ex_conversation_posting_status');
        $search_page_ids  = $this->session->userdata('facebook_ex_conversation_page_ids');
        $search_scheduled_from = $this->session->userdata('facebook_ex_conversation_scheduled_from');
        $search_scheduled_to = $this->session->userdata('facebook_ex_conversation_scheduled_to');

        $where_simple=array();
        
        if ($search_campaign_name) $where_simple['campaign_name like ']    = "%".$search_campaign_name."%";
        if ($search_posting_status) $where_simple['posting_status']    = $search_posting_status;
        if ($search_page_ids) $where_simple["FIND_IN_SET('$search_page_ids',  page_ids) !="] = 0;

        if ($search_scheduled_from) 
        {
            if ($search_scheduled_from != '1970-01-01') 
            $where_simple["Date_Format(schedule_time,'%Y-%m-%d') >="]= $search_scheduled_from;
            
        }
        if ($search_scheduled_to) 
        {
            if ($search_scheduled_to != '1970-01-01') 
            $where_simple["Date_Format(schedule_time,'%Y-%m-%d') <="]=$search_scheduled_to;
            
        }

        $where_simple['user_id'] = $this->user_id;
        $order_by_str=$sort." ".$order;
        $offset = ($page-1)*$rows;
        $where = array('where' => $where_simple);

        $table = "facebook_ex_conversation_campaign";
        $info = $this->basic->get_data($table,$where,$select='',$join='',$limit=$rows, $start=$offset,$order_by=$order_by_str);
        // echo $this->db->last_query();

        for($i=0;$i<count($info);$i++) 
        {
            if($info[$i]['schedule_time'] != "0000-00-00 00:00:00")
            $scheduled_at = date("M j, y H:i",strtotime($info[$i]['schedule_time']));
            else $scheduled_at = '<i class="fa fa-remove" title="Not scheduled"></i>';
            $info[$i]['scheduled_at'] =  $scheduled_at;

            if($info[$i]['added_at'] != "0000-00-00 00:00:00")
            $info[$i]['added_at'] = date("M j, y H:i",strtotime($info[$i]['added_at']));

            $posting_status = $info[$i]['posting_status'];

            if($posting_status=='1')
            $info[$i]["delete"] = '<i class="fa fa-remove" title="This campaign is in processing state"></i>';
            else $info[$i]['delete'] =  "<a title='Delete this campaign' id='".$info[$i]['id']."' class='delete btn-sm btn btn-danger'><i class='fa fa-trash'></i> Delete</a>";
            
            if( $posting_status == '2') $info[$i]['post_status_formatted'] = '<span class="label label-success"><i class="fa fa-check"></i> Completed</span>';
            else if( $posting_status == '1') $info[$i]['post_status_formatted'] = '<span class="label label-warning"><i class="fa fa-spinner"></i> Processing</span>';
            else $info[$i]['post_status_formatted'] = '<span class="label label-danger"><i class="fa fa-remove"></i> Pending</span>';
            
            if( $info[$i]['campaign_type'] == 'page-wise') $info[$i]['campaign_type_formatted'] = '<span class="label label-info">Multipage</span>';
            else $info[$i]['campaign_type_formatted'] = '<span class="label label-warning">Custom</span>';

            if($info[$i]['attached_video']!="") $info[$i]["attachment"] = "<a target='__BLANK' href='".$info[$i]['attached_video']."'><i class='fa fa-paperclip'></i></a>";
            else if($info[$i]['attached_url']!="") $info[$i]["attachment"] = "<a target='__BLANK' href='".$info[$i]['attached_url']."'><i class='fa fa-paperclip'></i></a>";
            else $info[$i]['attachment'] = '<i class="fa fa-remove" title="Not attachemnt"></i>';

            $info[$i]["sent_count"] =  $info[$i]["successfully_sent"]."/". $info[$i]["total_thread"] ;

            $info[$i]["page_names"] = implode(', ', json_decode($info[$i]["page_ids_names"],true));

            $info[$i]['report'] =  "<a title='View campaign report' cam-id='".$info[$i]['id']."' class='sent_report btn-sm btn btn-primary'><i class='fa fa-list'></i> Report</a>";
            
            if($posting_status!='0') $info[$i]['edit'] = '<i class="fa fa-remove" title="Only pending campaigns are editable"></i>';
            else
            {
                if($info[$i]["campaign_type"]=="page-wise")
                $edit_url = site_url('facebook_ex_campaign/edit_multipage_campaign/'.$info[$i]['id']);
                else
                $edit_url = site_url('facebook_ex_campaign/edit_custom_campaign/'.$info[$i]['id']);

                $info[$i]['edit'] =  "<a title='Edit Campaign' href='".$edit_url."' class='btn-sm btn btn-warning'><i class='fa fa-pencil'></i> Edit</a>";
            } 
        }



        $total_rows_array = $this->basic->count_row($table, $where, $count = "id");
        $total_result = $total_rows_array[0]['total_rows'];

        echo convert_to_grid_data($info, $total_result);
    }


    public function create_multipage_campaign()
    {
        $data['body'] = "facebook_ex/campaign/add_multipage_campaign";
        $data['page_title'] = $this->lang->line("Multipage campaign");
        $page_info = $this->basic->get_data("facebook_rx_fb_page_info",array("where"=>array("facebook_rx_fb_user_info_id"=>$this->session->userdata("facebook_rx_fb_user_info"),"user_id"=>$this->user_id,"current_subscribed_lead_count > "=>0)),array("page_id","page_name","id"));
        $data["time_zone"]= $this->_time_zone_list();
        $data['page_info'] = $page_info;
        $data['emotion_list'] = $this->get_emotion_list();
        $data["campaign_limit_status"]=$this->_check_usage($module_id=76,$request=1);  // for checking monthly campaign limit
        $this->_viewcontroller($data);
    }

    
    /********* SAMPLE REPORT FIELD FORMAT********/
    /*array
    (
        'page_id1'=>  // auto id
        array    
        (
            thread_id1 = > array("name1","sent_time1","message_id1","client_id1");
            thread_id2 = > array("name2","sent_time2","message_id2","client_id2");
            thread_id3 = > array("name3","sent_time3","message_id3","client_id3");
        ),

        'page_id2'=> array
        (
            thread_id1 = > array("name1","sent_time1","message_id1","client_id1");
            thread_id2 = > array("name2","sent_time2","message_id2","client_id2");
            thread_id3 = > array("name3","sent_time3","message_id3","client_id3");
        ),

        'page_id2'=> array
        (
            thread_id1 = > array("name1","sent_time1","message_id1","client_id1");
            thread_id2 = > array("name2","sent_time2","message_id2","client_id2");
            thread_id3 = > array("name3","sent_time3","message_id3","client_id3");
        )
    );*/
    /********* SAMPLE REPORT FIELD FORMAT********/


    public function create_multipage_campaign_action()
    {			
		if(!$_POST) exit();

        //************************************************//
        $status=$this->_check_usage($module_id=76,$request=1); 
        if($status=="3")  //monthly limit is exceeded, can not create another campaign this month
        exit();
        //************************************************//


        ignore_user_abort(TRUE); 
		
        $user_id = $this->user_id;
        $campaign_name = $this->input->post('campaign_name');
        $campaign_message = $this->input->post('message');
        $link = $this->input->post('link');
        $video_url = $this->input->post('video_url');
        $inbox_to_pages = $this->input->post('inbox_to_pages');
        
        $do_not_send = $this->input->post('do_not_send');
        if(!is_array($do_not_send)) $do_not_send = array();
       
        $schedule_type = $this->input->post('schedule_type');
        $schedule_time = $this->input->post('schedule_time');
        $time_zone = $this->input->post('time_zone');

        $delay_time = $this->input->post("delay_time");
        if($delay_time=="") $delay_time = 0;
        if($delay_time>15) $delay_time = 15;
        $unsubscribe_button = $this->input->post("unsubscribe_button");

        if($schedule_type == "now") $posting_status = "2";        
        else  $posting_status = "0";     

        $campaign_type= 'page-wise';          
        $added_at = date("Y-m-d H:i:s");
        $is_spam_caught = "0";
        $successfully_sent = 0;
        $total_thread = 0;

        $page_ids = array();
        $fb_page_ids = array();
        $page_id_association =array(); // fb_page_id => page_id 

        foreach ($inbox_to_pages as $key => $value) 
        {
            list($page_id, $fb_page_id) = explode('-', $value);
            $page_ids[] = $page_id;
            $fb_page_ids[] = $fb_page_id;
            $page_id_association[$fb_page_id] = $page_id; // which fb page id is which database auto id
        }

        $page_ids_names = array();
        $page_access_tokens = array();
        $page_info = $this->basic->get_data("facebook_rx_fb_page_info",array("where_in"=>array("id"=>$page_ids)),array("page_name","id","page_access_token"));
        foreach ($page_info as $key => $value) 
        {
            $page_ids_names[$value['id']] = $value['page_name']; // page names stored to database to show in grid
            $page_access_tokens[$value['id']] = $value['page_access_token']; // page access tokens of selected pages
        }
        
        $lead_list = $this->basic->get_data("facebook_rx_conversion_user_list",array("where"=>array("user_id"=>$this->user_id,"permission"=>"1"),"where_in"=>array("page_id"=>$fb_page_ids)));
        $report = array();

        $send_to_array=array(); // array of client id where message will be send
        foreach ($lead_list as $key => $value) 
        {
           if(in_array($value['client_thread_id'], $do_not_send)) continue;

           if(isset($send_to_array[$value["client_id"]])) continue; // so that same user in different pages does not recieve same message again and again
           $send_to_array[$value["client_id"]] = 1;

           $total_thread++;

           $get_page_auto_id = $page_id_association[$value['page_id']]; // page auto id to fb page id convsersion, facebook_rx_conversion_user_list dont have page auto id
           $report[$get_page_auto_id][$value['client_thread_id']] = array
           (
            "client_username"=>$value["client_username"],
            "client_id"=>$value["client_id"],
            "message_sent_id"=>"Pending",
            "sent_time"=>"Pending",
            "page_name" => "",
            "lead_id" =>  $value["id"]
            );

        }

        $campaign_message_db = $campaign_message;

        $data = array(
            'user_id' => $user_id,
            'page_ids' => implode(',',$page_ids), // comme seperated page auto id
            'fb_page_ids' => implode(',',$fb_page_ids), // comme seperated fb page id
            'page_ids_names' => json_encode($page_ids_names), //page auto id => page name associated array json
            'do_not_send_to' => json_encode($do_not_send), //exclude thread id array json
            'campaign_name' => $campaign_name,
            'campaign_type' => "page-wise",
            'campaign_message' => $campaign_message_db,
            'schedule_time' => $schedule_time,
            'time_zone' => $time_zone,
            'posting_status' => $posting_status,
            'is_spam_caught' => $is_spam_caught,
            'total_thread' => $total_thread,
            'successfully_sent' => $successfully_sent,
            'attached_url'=>$link,
            'attached_video'=>$video_url,
            'added_at' => $added_at,
            'report' => json_encode($report), // page and thread array json 
            'delay_time' => $delay_time,
            'unsubscribe_button' => $unsubscribe_button
        );

        //************************************************//
        $status=$this->_check_usage($module_id=79,$request=$total_thread); 
        if($status=="3")  //monthly limit is exceeded, can not send another ,message this month
        exit();
        //************************************************//

        $this->basic->insert_data('facebook_ex_conversation_campaign', $data); // at first campaign is insrted to database , then proccessed
       	$campaign_id= $this->db->insert_id();

        //******************************//
        // insert data to useges log table
        $this->_insert_usage_log($module_id=76,$request=1);   
        //******************************//

        //******************************//
        // insert data to useges log table (message count)
        $this->_insert_usage_log($module_id=79,$request=$total_thread);   
        //******************************//
  
		
		//send now then start sending message 
		if($schedule_type == "now")
        {				
    		$i=0;
            $send_report = array();
            $is_spam_caught_send = "0"; // is facebook marked this message as spam?
            $catch_error_count = 0; // catch block error count

    		foreach($report as $key=>$value)
            {			
    			if($catch_error_count>10) break;  // if 10 catch block error then stop sending

                $page_id_send  = $key;
                foreach ($value as $key2 => $value2) 
                {
                    if($catch_error_count>10) break; // if 10 catch block error then stop sending

                    $client_thread_id_send = $key2;
                    $client_id_send = $value2['client_id'];
                    $client_username_send = $value2['client_username'];
                    // added by mostofa at 04/03/2017 
                    $client_username_send_array = explode(' ', $client_username_send);
                    $client_last_name = array_pop($client_username_send_array);
                    $client_first_name = implode(' ', $client_username_send_array); 

                    $page_access_token_send = $page_access_tokens[$page_id_send];

                    //  generating message
                    $campaign_message_send = $campaign_message;
                    $campaign_message_send = str_replace('#LEAD_USER_NAME#',$client_username_send,$campaign_message_send);
                    // added by mostofa at 04/03/2017 
                    $campaign_message_send = str_replace('#LEAD_USER_FIRST_NAME#',$client_first_name,$campaign_message_send);
                    $campaign_message_send = str_replace('#LEAD_USER_LAST_NAME#',$client_last_name,$campaign_message_send);

                    if($video_url!="") $campaign_message_send = $campaign_message_send."\n".$video_url;
                    else if($link!="") $campaign_message_send = $campaign_message_send."\n".$link;

                    // generate unsubscribe link
                    if($unsubscribe_button=="1")
                    {
                        $code = $this->_random_number_generator(6)."_".$value2["lead_id"]."_".$page_id_send."_".$this->_random_number_generator(6);
                        $code= base64_encode($code);
                        $code= urlencode($code);
                        $unsubscribe_link =site_url("home/ul/".$code);
                        $campaign_message_send = $campaign_message_send."\n\nUnsubscribe link : \n".$unsubscribe_link."\n";
                    }

                    $error_msg="";
                    $message_error_code = "";
                    $message_sent_id = "";
                    try
                    {
                        $response = $this->fb_rx_login->send_message_to_thread($client_thread_id_send,$campaign_message_send,$page_access_token_send);
                        if(isset($response['id']))
                        {
                            $message_sent_id = $response['id']; 
                            $successfully_sent++; 
                        }
                        else 
                        {
                            if(isset($response["error"]["message"])) $message_sent_id = $response["error"]["message"];  
                            if(isset($response["error"]["code"])) $message_error_code = $response["error"]["code"]; 

                            if($message_error_code=="368") // if facebook marked message as spam 
                            {
                                $error_msg=$message_sent_id;
                                $is_spam_caught_send = "1";
                            }

                            if($message_error_code=="230") //user blocked page
                            {
                                $this->basic->update_data("facebook_rx_conversion_user_list",array("id"=>$value2["lead_id"]),array("permission"=>"0"));
                                if($this->db->affected_rows()>0)
                                {
                                    $this->basic->execute_complex_query("UPDATE facebook_rx_fb_page_info SET current_subscribed_lead_count=current_subscribed_lead_count-1,current_unsubscribed_lead_count=current_unsubscribed_lead_count+1 WHERE id='{$page_id_send}'");
                                }
                            }
                        } 

                        if($delay_time==0)
                        sleep(rand(3,12));
                        else sleep($delay_time);                
                        
                    }

                    catch(Exception $e) 
                    {
                      $error_msg = $e->getMessage();
                      $catch_error_count++;
                    }
                    
                    // generating new report with send message info
                    $send_report[$page_id_send][$client_thread_id_send] = array
                    ( 
                        "client_username"=>$client_username_send,
                        "client_id"=>$client_id_send,
                        "message_sent_id"=> $message_sent_id,
                        "sent_time"=> date("Y-m-d H:i:s"),
                        "page_name" => $page_ids_names[$page_id_send]
                    ); 

                    $i++; 
                    // after 10 send update report in database
                    if($i%10==0)
                    {
                        $send_report_json= json_encode($send_report);
                        $this->basic->update_data("facebook_ex_conversation_campaign",array("id"=>$campaign_id),array("report"=>$send_report_json,'successfully_sent'=>$successfully_sent,"error_message"=>$error_msg));
                    } 

                    if($message_error_code=="368") break;  // if facebook marked message as spam , then stop sending
                                
                }

    			if($message_error_code=="368") break; // if facebook marked message as spam , then stop sending

    			
    		}	

    		// everything is done, now update database finally
            $send_report_json= json_encode($send_report);
    		$this->basic->update_data("facebook_ex_conversation_campaign",array("id"=>$campaign_id),array("report"=>$send_report_json,"posting_status"=>'2','successfully_sent'=>$successfully_sent,'completed_at'=>date("Y-m-d H:i:s"),"is_spam_caught"=>$is_spam_caught_send,"error_message"=>$error_msg));

        } // end of now block

		
    }

    public function edit_multipage_campaign($id=0)
    {
        if($id==0) exit();

        $data['body'] = "facebook_ex/campaign/edit_multipage_campaign";
        $data['page_title'] = $this->lang->line("Edit multipage campaign");
        $page_info = $this->basic->get_data("facebook_rx_fb_page_info",array("where"=>array("facebook_rx_fb_user_info_id"=>$this->session->userdata("facebook_rx_fb_user_info"),"user_id"=>$this->user_id,"current_subscribed_lead_count > "=>0)),array("page_id","page_name","id"));
        $data["time_zone"]= $this->_time_zone_list();
        $data['page_info'] = $page_info;
        $data['emotion_list'] = $this->get_emotion_list();
        $data["xdata"] = $this->basic->get_data("facebook_ex_conversation_campaign",array("where"=>array("id"=>$id,"user_id"=>$this->user_id)));

        // only pending campaigns are editable
        if(!isset($data["xdata"][0]["posting_status"]) || $data["xdata"][0]["posting_status"]!='0' ) exit();

        $previous_exclude = isset($data["xdata"][0]["do_not_send_to"]) ? json_decode($data["xdata"][0]["do_not_send_to"],true) : array();

        $data["xdo_not_send_to"]=array();
        if(count($previous_exclude)>0)
        $data["xdo_not_send_to"] = $this->basic->get_data("facebook_rx_conversion_user_list",array("where_in"=>array("client_thread_id"=>$previous_exclude,"user_id"=>$this->user_id)));
       
        $this->_viewcontroller($data);
    }

    public function edit_multipage_campaign_action()
    {           
        if(!$_POST) exit();
        ignore_user_abort(TRUE); 

        $campaign_id = $this->input->post("campaign_id");
        $previous_thread = $this->input->post("previous_thread");
        
        $user_id = $this->user_id;
        $campaign_name = $this->input->post('campaign_name');
        $campaign_message = $this->input->post('message');
        $link = $this->input->post('link');
        $video_url = $this->input->post('video_url');
        $inbox_to_pages = $this->input->post('inbox_to_pages');
        
        $do_not_send = $this->input->post('do_not_send');
        if(!is_array($do_not_send)) $do_not_send = array();
       
        $schedule_type = $this->input->post('schedule_type');
        $schedule_time = $this->input->post('schedule_time');
        $time_zone = $this->input->post('time_zone');

        $delay_time = $this->input->post("delay_time");
        if($delay_time=="") $delay_time = 0;
        if($delay_time>15) $delay_time = 15;
        $unsubscribe_button = $this->input->post("unsubscribe_button");

        if($schedule_type == "now") $posting_status = "2";        
        else  $posting_status = "0";     

        $campaign_type= 'page-wise';          
        $added_at = date("Y-m-d H:i:s");
        $is_spam_caught = "0";
        $successfully_sent = 0;
        $total_thread = 0;

        $page_ids = array();
        $fb_page_ids = array();
        $page_id_association =array(); // fb_page_id => page_id 

        foreach ($inbox_to_pages as $key => $value) 
        {
            list($page_id, $fb_page_id) = explode('-', $value);
            $page_ids[] = $page_id;
            $fb_page_ids[] = $fb_page_id;
            $page_id_association[$fb_page_id] = $page_id; // which fb page id is which database auto id
        }

        $page_ids_names = array();
        $page_access_tokens = array();
        $page_info = $this->basic->get_data("facebook_rx_fb_page_info",array("where_in"=>array("id"=>$page_ids)),array("page_name","id","page_access_token"));
        foreach ($page_info as $key => $value) 
        {
            $page_ids_names[$value['id']] = $value['page_name']; // page names stored to database to show in grid
            $page_access_tokens[$value['id']] = $value['page_access_token']; // page access tokens of selected pages
        }
        
        $lead_list = $this->basic->get_data("facebook_rx_conversion_user_list",array("where"=>array("user_id"=>$this->user_id,"permission"=>"1"),"where_in"=>array("page_id"=>$fb_page_ids)));
        $report = array();

        $send_to_array=array();
        foreach ($lead_list as $key => $value) 
        {
           if(in_array($value['client_thread_id'], $do_not_send)) continue;

           if(isset($send_to_array[$value["client_id"]])) continue; // so that same user in different pages does not recieve same message again and again
           $send_to_array[$value["client_id"]] = 1;

           $total_thread++;

           $get_page_auto_id = $page_id_association[$value['page_id']]; // page auto id to fb page id convsersion, facebook_rx_conversion_user_list dont have page auto id
           $report[$get_page_auto_id][$value['client_thread_id']] = array
           (
            "client_username"=>$value["client_username"],
            "client_id"=>$value["client_id"],
            "message_sent_id"=>"Pending",
            "sent_time"=>"Pending",
            "page_name" => "",
            "lead_id" =>  $value["id"]
            );

        }

        $campaign_message_db = $campaign_message;
     
        $data = array(
            'page_ids' => implode(',',$page_ids), // comme seperated page auto id
            'fb_page_ids' => implode(',',$fb_page_ids), // comme seperated fb page id
            'page_ids_names' => json_encode($page_ids_names), //page auto id => page name associated array json
            'do_not_send_to' => json_encode($do_not_send), //exclude thread id array json
            'campaign_name' => $campaign_name,            
            'campaign_message' => $campaign_message_db,
            'schedule_time' => $schedule_time,
            'time_zone' => $time_zone,
            'posting_status' => $posting_status,
            'is_spam_caught' => $is_spam_caught,
            'total_thread' => $total_thread,
            'successfully_sent' => $successfully_sent,
            'attached_url'=>$link,
            'attached_video'=>$video_url,
            'report' => json_encode($report), // page and thread array json 
            'delay_time' => $delay_time,
            'unsubscribe_button' => $unsubscribe_button
        );

        $current_total_thread = $previous_thread - $total_thread;
        $current_total_thread_abs = abs($current_total_thread);



        //************************************************//
        if($current_total_thread<0)
        {
            $status=$this->_check_usage($module_id=79,$request=$total_thread); 
            if($status=="3")  //monthly limit is exceeded, can not send another ,message this month
            exit();
        }
        //************************************************//

        $this->basic->update_data('facebook_ex_conversation_campaign',array("id"=>$campaign_id,"user_id"=>$this->user_id),$data); // at first campaign is insrted to database , then proccessed
   
     
        //******************************//
        // insert data to useges log table (message count)
        if($current_total_thread<0)
        $this->_insert_usage_log($module_id=79,$request=$current_total_thread_abs);   
        else $this->_delete_usage_log($module_id=79,$request=$current_total_thread_abs); 
        //******************************//
        
        //send now then start sending message 
        if($schedule_type == "now")
        {               
            $i=0;
            $send_report = array();
            $is_spam_caught_send = "0"; // is facebook marked this message as spam?
            $catch_error_count = 0; // catch block error count

            foreach($report as $key=>$value)
            {           
                if($catch_error_count>10) break;  // if 10 catch block error then stop sending

                $page_id_send  = $key;
                foreach ($value as $key2 => $value2) 
                {
                    if($catch_error_count>10) break; // if 10 catch block error then stop sending

                    $client_thread_id_send = $key2;
                    $client_id_send = $value2['client_id'];
                    $client_username_send = $value2['client_username'];
                    // added by mostofa at 04/03/2017 
                    $client_username_send_array = explode(' ', $client_username_send);
                    $client_last_name = array_pop($client_username_send_array);
                    $client_first_name = implode(' ', $client_username_send_array);

                    $page_access_token_send = $page_access_tokens[$page_id_send];

                    //  generating message
                    $campaign_message_send = $campaign_message;
                    $campaign_message_send = str_replace('#LEAD_USER_NAME#',$client_username_send,$campaign_message_send);
                    // added by mostofa at 04/03/2017 
                    $campaign_message_send = str_replace('#LEAD_USER_FIRST_NAME#',$client_first_name,$campaign_message_send);
                    $campaign_message_send = str_replace('#LEAD_USER_LAST_NAME#',$client_last_name,$campaign_message_send);

                    if($video_url!="") $campaign_message_send = $campaign_message_send."\n".$video_url;
                    else if($link!="") $campaign_message_send = $campaign_message_send."\n".$link;

                    // generate unsubscribe link
                    if($unsubscribe_button=="1")
                    {
                        $code = $this->_random_number_generator(6)."_".$value2["lead_id"]."_".$page_id_send."_".$this->_random_number_generator(6);
                        $code= base64_encode($code);
                        $code= urlencode($code);
                        $unsubscribe_link =site_url("home/ul/".$code);
                        $campaign_message_send = $campaign_message_send."\n\nUnsubscribe link : \n".$unsubscribe_link."\n";
                    }

                    $error_msg="";
                    $message_error_code = "";
                    $message_sent_id = "";
                    try
                    {
                        $response = $this->fb_rx_login->send_message_to_thread($client_thread_id_send,$campaign_message_send,$page_access_token_send);
                        if(isset($response['id']))
                        {
                            $message_sent_id = $response['id']; 
                            $successfully_sent++; 
                        }
                        else 
                        {
                            if(isset($response["error"]["message"])) $message_sent_id = $response["error"]["message"];  
                            if(isset($response["error"]["code"])) $message_error_code = $response["error"]["code"]; 

                            if($message_error_code=="368") // if facebook marked message as spam 
                            {
                                $error_msg=$message_sent_id;
                                $is_spam_caught_send = "1";
                            }

                            if($message_error_code=="230") //user blocked page
                            {
                                $this->basic->update_data("facebook_rx_conversion_user_list",array("id"=>$value2["lead_id"]),array("permission"=>"0"));
                                if($this->db->affected_rows()>0)
                                {
                                    $this->basic->execute_complex_query("UPDATE facebook_rx_fb_page_info SET current_subscribed_lead_count=current_subscribed_lead_count-1,current_unsubscribed_lead_count=current_unsubscribed_lead_count+1 WHERE id='{$page_id_send}'");
                                }
                            }
                        }  

                        if($delay_time==0)
                        sleep(rand(3,12));
                        else sleep($delay_time);                 
                        
                    }

                    catch(Exception $e) 
                    {
                      $error_msg = $e->getMessage();
                      $catch_error_count++;
                    }
                    
                    // generating new report with send message info
                    $send_report[$page_id_send][$client_thread_id_send] = array
                    ( 
                        "client_username"=>$client_username_send,
                        "client_id"=>$client_id_send,
                        "message_sent_id"=> $message_sent_id,
                        "sent_time"=> date("Y-m-d H:i:s"),
                        "page_name" => $page_ids_names[$page_id_send]
                    ); 

                    $i++;  
                    // after 10 send update report in database
                    if($i%10==0)
                    {
                        $send_report_json= json_encode($send_report);
                        $this->basic->update_data("facebook_ex_conversation_campaign",array("id"=>$campaign_id),array("report"=>$send_report_json,'successfully_sent'=>$successfully_sent,"error_message"=>$error_msg));
                    }

                    if($message_error_code=="368") break;  // if facebook marked message as spam , then stop sending
                                
                }

                if($message_error_code=="368") break; // if facebook marked message as spam , then stop sending

                
            }   

            // everything is done, now update database finally
            $send_report_json= json_encode($send_report);
            $this->basic->update_data("facebook_ex_conversation_campaign",array("id"=>$campaign_id),array("report"=>$send_report_json,"posting_status"=>'2','successfully_sent'=>$successfully_sent,'completed_at'=>date("Y-m-d H:i:s"),"is_spam_caught"=>$is_spam_caught_send,"error_message"=>$error_msg));
         
        } // end of now block

        
    }


    public function send_test_message()
    {
        
        if(!$_POST) exit();

        $campaign_message = $this->input->post("message");
        $link = $this->input->post("link");
        $video_url = $this->input->post("video_url");
        $thread_ids = $this->input->post("thread_ids");
        $thread_ids = array_unique($thread_ids);
        $total_thread = count($thread_ids);

        $successfully_sent=0;
        $join = array('facebook_rx_fb_page_info'=>"facebook_rx_fb_page_info.page_id=facebook_rx_conversion_user_list.page_id,left");
        $where = array("where_in"=>array("client_thread_id"=>$thread_ids,"facebook_rx_conversion_user_list.user_id"=>array($this->user_id),"facebook_rx_fb_page_info.user_id"=>array($this->user_id)));
        $info = $this->basic->get_data("facebook_rx_conversion_user_list",$where,array("page_access_token","client_username","client_thread_id","client_id"),$join,'','','client_username asc','client_thread_id');
        $str="";
        $str.= "<table class='table table-condensed table-bordered table-hover table-striped'><caption class='text-center blue'>Test Message Report</caption>";
        $str.=  "<tr>";
            $str.=  "<th>"."SL"."</th>";
            $str.=  "<th>"."Client Username"."</th>";
            $str.=  "<th>"."Status"."</th>";
            $str.=  "<th>"."Message ID"."</th>";
        $str.=  "</tr>";
        $sl=0;
        foreach ($info as $key => $value) 
        {            
            $sl++;
            $client_username_send = $value["client_username"];
            $client_thread_id_send = $value["client_thread_id"];
            $client_id_send = $value["client_id"];
            $page_access_token_send = $value["page_access_token"];
           
            $campaign_message_send = $campaign_message;
            // added by mostofa at 04/03/2017 
            $client_username_send_array = explode(' ', $client_username_send);
            $client_last_name = array_pop($client_username_send_array);
            $client_first_name = implode(' ', $client_username_send_array);

            $campaign_message_send = str_replace('#LEAD_USER_NAME#',$client_username_send,$campaign_message_send);
            // added by mostofa at 04/03/2017 
            $campaign_message_send = str_replace('#LEAD_USER_FIRST_NAME#',$client_first_name,$campaign_message_send);
            $campaign_message_send = str_replace('#LEAD_USER_LAST_NAME#',$client_last_name,$campaign_message_send);

            if($video_url!="") $campaign_message_send = $campaign_message_send."\n".$video_url;
            else if($link!="") $campaign_message_send = $campaign_message_send."\n".$link;   

            $campaign_message_send = $campaign_message_send."\n\n"."[This message is sent for test purpose using 'FB Inboxer', to see actual inbox preview.]";      
            
            $error_msg="<span class='label label-success'><i class='fa fa-check'></i> Successful</span>";
            $message_sent_id = "";            
            try
            {
                $response = $this->fb_rx_login->send_message_to_thread($client_thread_id_send,$campaign_message_send,$page_access_token_send);
                if(isset($response['id']))
                {
                    $message_sent_id = $response['id']; 
                    $successfully_sent++; 
                }
                else 
                {
                    if(isset($response["error"]["message"])) $error_msg = $response["error"]["message"];                      
                }               
                
            }

            catch(Exception $e) 
            {
               $error_msg = $e->getMessage();
            }

            $user_link = "<a class='blue' target='_BLANK' href='https://facebook.com/".$client_id_send."'>".$client_username_send."</a>";

            $str.=  "<tr>";
                $str.=  "<td>".$sl."</td>";
                $str.=  "<td>".$user_link."</td>";
                $str.=  "<td>".$error_msg."</td>";
                $str.=  "<td>".$message_sent_id."</td>";
            $str.=  "</tr>";

        }
        $str.= "</table>";
        echo $str;

        
    }

    public function meta_info_grabber()
    {
        if($_POST)
        {
            $link= $this->input->post("link");
            $response=$this->fb_rx_login->get_meta_tag_fb($link);
            echo json_encode($response);
        }
    } 

    public function count_total_inbox()
    {
        $page_ids = $this->input->post("fb_page_ids");
        $previous_thread = $this->input->post("previous_thread"); // used for edit only , previous thread have to substract when calculate new message sending limt
        
        //count = currently selected lead count, messge_limit_exceeded = monlty inbox send status
        if(!is_array($page_ids))
        {
            echo json_encode(array("count"=>0,"message_limit_exceeded"=>"0"));
            exit();
        }  
        
        $count_data = $this->basic->get_data("facebook_rx_fb_page_info",array("where"=>array("facebook_rx_fb_user_info_id"=>$this->session->userdata("facebook_rx_fb_user_info")),"where_in"=>array("page_id"=>$page_ids)),"sum(current_subscribed_lead_count) as countnumber");
       
        if(isset($count_data[0]["countnumber"]) && $count_data[0]["countnumber"]>0) 
        {
           $no_of_request = $count_data[0]["countnumber"];

           if($previous_thread!="" && $previous_thread>0) // used ony for edit
           {
                if($no_of_request > $previous_thread)
                $no_of_request = $no_of_request - $previous_thread;
           }

           $message_limit_exceeded=$this->_check_usage($module_id=79,$request=$no_of_request); // checking if user is allowed to send this ammount of message
           
           if($message_limit_exceeded=="3")  // monthly limit exceeded
           {
                echo json_encode(array("count"=>$count_data[0]["countnumber"],"message_limit_exceeded"=>"1"));
                exit();
           }
           else             
           {
               echo json_encode(array("count"=>$count_data[0]["countnumber"],"message_limit_exceeded"=>"0"));
               exit();
           }
        }
        echo json_encode(array("count"=>0,"message_limit_exceeded"=>"0"));
    }


    public function lead_autocomplete($check_permission=1,$page_ids_str="")
    {
       $search_query= $this->input->get('search');

       $page_ids = array();
       if($page_ids_str!="")  //  - seperated fb page ids
       $page_ids = explode('-', $page_ids_str);       

       $this->db->select(array('facebook_rx_fb_page_info.page_name','facebook_rx_conversion_user_list.*'));
       $this->db->from('facebook_rx_conversion_user_list');
       $this->db->like('client_username', $search_query);
       $this->db->order_by('client_username', 'ASC');
       $this->db->group_by('client_thread_id');
       $this->db->where("facebook_rx_conversion_user_list.user_id",$this->user_id);
       $this->db->where("facebook_rx_fb_page_info.user_id",$this->user_id);
       $this->db->join("facebook_rx_fb_page_info","facebook_rx_fb_page_info.page_id=facebook_rx_conversion_user_list.page_id",'left');
       
       if($check_permission==1)  // if check permission is 1 then it will only grab subscribed users
       $this->db->where("permission","1");
       
       if(count($page_ids)>0)  // if facebook page ids is passed then it will filter by page ids, may be needed later, not used for now
       $this->db->where_in("facebook_rx_conversion_user_list.page_id",$page_ids);     
       
       $this->db->limit(30);
       $data=$this->db->get()->result_array();
       $results=array();

       foreach ($data as $key => $value) 
       {
          $results[]=array("value"=>$value["client_thread_id"],"text"=>$value["client_username"]." (".$value["page_name"].")");
       }
       echo json_encode($results);
    }

    public function youtube_video_grabber()
    {
        if(!$_POST) exit();
        $video_url = $this->input->post("link");

        $response = array("status"=>"0","title"=>"","description"=>"","video_embed"=>"");

        if($video_url!="")
        {
            if(strpos($video_url, 'youtube.com') !== false || strpos($video_url, 'youtu.be')!==false) 
            {
                if(strpos($video_url, 'youtube.com') !== false)
                {
                    parse_str( parse_url( $video_url, PHP_URL_QUERY ), $my_array_of_vars );
                    $youtube_video_id = isset($my_array_of_vars['v']) ? $my_array_of_vars['v'] : "";
                }
                else
                {   
                    $video_url_replced= str_replace('//','',$video_url);
                    $explode_url =explode('/',$video_url_replced);
                    $youtube_video_id = array_pop($explode_url);
                }                
                
                
                $video_data = $this->fb_rx_login->get_meta_tag_fb($video_url);              

                $response["status"] ="1";
                $response["video_embed"] = '<iframe width="100%" height="100" src="https://www.youtube.com/embed/'.$youtube_video_id.'" frameborder="0" allowfullscreen></iframe>'; 
                $response["title"] = isset($video_data["title"]) ? $video_data["title"] : "";
                $response["description"] = isset($video_data["description"]) ? $video_data["description"] : "";
               
            }
        }

        echo json_encode($response);

    }


    public function link_grabber()
    {
        if(!$_POST) exit();
        $video_url = $this->input->post("link");

        $response = array("status"=>"0","title"=>"","description"=>"","image"=>"");

        if($video_url!="" && (strpos($video_url, 'http://') !== false || strpos($video_url, 'https://') !== false))
        {         
            if (!preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",$video_url)) 
            {
                echo json_encode($response);
                exit();
            }
            
            $video_data = $this->fb_rx_login->get_meta_tag_fb($video_url);              

            $response["status"] ="1";
            $response["title"] = isset($video_data["title"]) ? $video_data["title"] : "";
            $response["description"] = isset($video_data["description"]) ? $video_data["description"] : "";
            $response["image"] = isset($video_data["image"]) ? $video_data["image"] : "";               
        }

        echo json_encode($response);

    }


    public function scanAll($myDir)
    {
        $dirTree = array();
        $di = new RecursiveDirectoryIterator($myDir,RecursiveDirectoryIterator::SKIP_DOTS);

        $i=0;
        foreach (new RecursiveIteratorIterator($di) as $filename) {

            $dir = str_replace($myDir, '', dirname($filename));
            $dir = str_replace('/', '>', substr($dir,1));

            $org_dir=str_replace("\\", "/", $dir);

            if($org_dir)
                $file_path = $org_dir. "/". basename($filename);
            else
                $file_path = basename($filename);

            $file_full_path=$myDir."/".$file_path;
            $file_size= filesize($file_full_path);
            $file_modification_time=filemtime($file_full_path);

            $dirTree[$i]['file'] = $file_full_path;
            $dirTree[$i]['size'] = $file_size;
            $dirTree[$i]['time'] =date("Y-m-d H:i:s",$file_modification_time);

            $i++;

        }

        return $dirTree;
    }


    public function get_emotion_list()
    {
        $dirTree=$this->scanAll(FCPATH."assets/images/emotions-fb");
        $map = array
            (
                "angel" => "o:)",
                "colonthree" => ":3",
                "confused" => "o.O",
                "cry" => ":'(",
                "devil" => "3:)",
                "frown" => ":(",
                "gasp" => ":O",
                "glasses" => "8)",
                "grin" => ":D",
                "grumpy" => ">:(",
                "heart" => "<3",
                "kiki" => "^_^",
                "kiss" => ":*",
                "pacman" => ":v",
                "smile" => ":)",
                "squint" => "-_-",
                "sunglasses" => "8|",
                "tongue" => ":p",
                "upset" => ">:O",
                "wink" => ";)"
            );
        $str = "";
        foreach ($dirTree as $value) 
        {
            $temp = array();
            $value['file'] = str_replace('\\','/', $value['file']);
            $temp =explode('/', $value["file"]);
            $filename = array_pop($temp);

            if(!strpos($filename,'.gif')) continue;

            $title = str_replace('.gif',"",$filename);
            $eval = $map[$title];

            $src= base_url('assets/images/emotions-fb/'.$filename);
            $str.= '&nbsp;&nbsp;<img eval="'.$eval.'" title="'.$title.'" style="cursor:pointer;"  class="emotion inline" src="'.$src.'"/>&nbsp;&nbsp;';
        }
        return $str;
            

    }
  
    public function campaign_sent_status()
    {
        if(!$_POST) exit();
        $id = $this->input->post("id");

        $campaign_data = $this->basic->get_data("facebook_ex_conversation_campaign",array("where"=>array("id"=>$id,"user_id"=>$this->user_id)));
        $report = isset($campaign_data[0]["report"]) ? json_decode($campaign_data[0]["report"],true) : array();
        $campaign_name  = isset($campaign_data[0]["campaign_name"]) ? $campaign_data[0]["campaign_name"] : "";
        $is_spam_caught  = isset($campaign_data[0]["is_spam_caught"]) ? $campaign_data[0]["is_spam_caught"] : 0;
        $total_thread  = isset($campaign_data[0]["total_thread"]) ? $campaign_data[0]["total_thread"] : 0;
        $successfully_sent  = isset($campaign_data[0]["successfully_sent"]) ? $campaign_data[0]["successfully_sent"] : 0;
        $error_message  = isset($campaign_data[0]["error_message"]) ? $campaign_data[0]["error_message"] : "";
        $video_url  = isset($campaign_data[0]["attached_video"]) ? $campaign_data[0]["attached_video"] : "";
        $link  = isset($campaign_data[0][" attached_url"]) ? $campaign_data[0]["  attached_url"] : "";
        $campaign_message  = isset($campaign_data[0]["campaign_message"]) ? $campaign_data[0]["campaign_message"] : "";

        $campaign_message_send = $campaign_message;
        if($video_url!="") $campaign_message_send = $campaign_message_send."\n"."<a target='_BLANK' href='{$video_url}'>{$video_url}</a>";
        else if($link!="") $campaign_message_send = $campaign_message_send."\n"."<a target='_BLANK' href='{$link}'>{$link}</a>";  

        $posting_status = $campaign_data[0]['posting_status'];
        if( $posting_status == '2') $posting_status = '<span class="label label-success"><i class="fa fa-check"></i> Completed</span>';
        else if( $posting_status == '1') $posting_status = '<span class="label label-warning"><i class="fa fa-spinner"></i> Processing</span>';
        else $posting_status = '<span class="label label-danger"><i class="fa fa-remove"></i> Pending</span>';


        $response = "";
        if(count($report)==0) 
        {
            $response.= "<h4><div class='alert alert-warning text-center'>No data found for campaign <b>'".$campaign_name."'</b>.</div></h4>";
            echo $response;
            exit();
        }

        $response .= '<script>
                    $j(document).ready(function() {
                        $("#campaign_report").DataTable();
                    }); 
                 </script>';

       
        $response .= "<h4><span class='pull-left'></span>".$campaign_name."<span class='pull-right'>".$posting_status."</span></h4><div class='clearfix'></div>";
        $response .= "<h4 class='text-center'><div class='well blue' style='padding:7px;margin:0;'>Successfully sent {$successfully_sent} message out of {$total_thread}</div></h4>";
        
        if($is_spam_caught==1)
        $spam_text = "Campaign was marked as spam. <br/> ";
        else $spam_text="";

        if($error_message!="")
        $response .= "<div class='alert alert-danger text-center'> {$spam_text} Something went wrong for one or more message. Original error message : {$error_message}</div>";       

        $response .="<div class='table-responsive'>";
        $response .="<table id='campaign_report' class='table table-hover table-bordered table-striped table-condensed nowrap'>";
        $response .= "<thead><tr>";
        $response .= "<th>SL.</th>";
        $response .= "<th>Client Username</th>";
        $response .= "<th>Sent at</th>";
        $response .= "<th>Page Name</th>";
        $response .= "<th>Message ID / Status</th>";
        $response .= "</tr></thead>";
        $i=0;

        foreach ($report as $key2 => $value2) 
        {
          foreach ($value2 as $key => $value) 
          {
                if(!isset($value["client_id"])) $value["client_id"] = "";
                if(!isset($value["client_username"])) $value["client_username"] = "";
                if(!isset($value["sent_time"])) $value["sent_time"] = "0000-00-00 00:00:00";
                if(!isset($value["page_name"])) $value["page_name"] = "";
                if(!isset($value["message_sent_id"])) $value["message_sent_id"] = "";

                $i++;
                $response .= "<tr>";
                $response .= "<th>".$i."</th>";
                $response .= "<th><a target='_BLANK' href='http://facebook.com/".$value["client_id"]."'>".$value["client_username"]."</a></th>";
                $response .= "<th>".date("M j, y H:i",strtotime($value["sent_time"]))."</th>";                
                $response .= "<th>".$value["page_name"]."</th>";
                $response .= "<th>".$value["message_sent_id"]."</th>";
                $response .= "</tr>";
          }
        }
        $response .= "</table></div>";
        $response.="<br/><div class='well'><h5 class='blue'>Original Message : </h5>".nl2br($campaign_message_send)."</div>";

        echo $response;
    }


    public function custom_campaign()
    {
        $data['body'] = "facebook_ex/campaign/add_custom_campaign";
        $data['page_title'] = $this->lang->line("Custom Campaign");
        $page_info = $this->basic->get_data("facebook_rx_fb_page_info",array("where"=>array("facebook_rx_fb_user_info_id"=>$this->session->userdata("facebook_rx_fb_user_info"),"user_id"=>$this->user_id)),array("page_id","page_name","id"));
        $data["time_zone"]= $this->_time_zone_list();
        $data['page_info'] = $page_info;
        $data['emotion_list'] = $this->get_emotion_list();
        $data["campaign_limit_status"]=$this->_check_usage($module_id=76,$request=1);  // for checking monthly campaign limit
        $this->_viewcontroller($data);
    }

    public function custom_campaign_data()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET')
        redirect('home/access_forbidden', 'location');

        $page = isset($_POST['page']) ? intval($_POST['page']) : 15;
        $rows = isset($_POST['rows']) ? intval($_POST['rows']) : 5;
        $sort = isset($_POST['sort']) ? strval($_POST['sort']) : 'id';
        $order = isset($_POST['order']) ? strval($_POST['order']) : 'DESC';


        $client_username = trim($this->input->post("search_client_username", true));
        $page_id = trim($this->input->post("search_page", true)); // fb page id not auto id
        $is_searched = $this->input->post('is_searched', true);

        if($is_searched) 
        {
            $this->session->set_userdata('facebook_ex_conversation_custom_username', $client_username);
            $this->session->set_userdata('facebook_ex_conversation_custom_page_id', $page_id);
        }

        $search_client_username  = $this->session->userdata('facebook_ex_conversation_custom_username');
        $search_page_id  = $this->session->userdata('facebook_ex_conversation_custom_page_id');

        $where_simple=array();
        
        if ($search_client_username) $where_simple['client_username like '] = "%".$search_client_username."%";
        if ($search_page_id) $where_simple['facebook_rx_conversion_user_list.page_id'] = $search_page_id;
 
        $where_simple['facebook_rx_conversion_user_list.user_id'] = $this->user_id;
        $where_simple['facebook_rx_fb_page_info.user_id'] = $this->user_id;
        $where_simple['facebook_rx_conversion_user_list.permission'] = '1';
        $order_by_str=$sort." ".$order;
        $offset = ($page-1)*$rows;
        $where = array('where' => $where_simple);

        $join = array('facebook_rx_fb_page_info'=>"facebook_rx_fb_page_info.page_id=facebook_rx_conversion_user_list.page_id,left");
        $select =array("facebook_rx_conversion_user_list.*","facebook_rx_fb_page_info.page_name","facebook_rx_fb_page_info.id as page_auto_id");

        $table = "facebook_rx_conversion_user_list";
        $info = $this->basic->get_data($table,$where,$select,$join,$limit=$rows, $start=$offset,$order_by=$order_by_str,"client_thread_id");
    
        for($i=0;$i<count($info);$i++) 
        {
           
            $info[$i]['page_name_formatted'] = "<a  target='_BLANK' href='https://facebook.com/".$info[$i]['page_id']."'>".$info[$i]['page_name']."</a>";
            $info[$i]['client_username_formatted'] = "<a  target='_BLANK' href='https://facebook.com/".$info[$i]['client_id']."'>".$info[$i]['client_username']."</a>";

        }

        $total_rows_array = $this->basic->count_row($table, $where, $count = "facebook_rx_conversion_user_list.id",$join,'client_thread_id');
        $total_result = $total_rows_array[0]['total_rows'];

        echo convert_to_grid_data($info, $total_result);
    }


    public function create_custom_campaign_action()
    {          

        if(!$_POST) exit();

        //************************************************//
        $status=$this->_check_usage($module_id=76,$request=1); 
        if($status=="3")  //monthly limit is exceeded, can not create another campaign this month
        exit();
        //************************************************//

        ignore_user_abort(TRUE); 
        
        $user_id = $this->user_id;
        $campaign_name = $this->input->post('campaign_name');
        $campaign_message = $this->input->post('message');
        $link = $this->input->post('link');
        $video_url = $this->input->post('video_url');
        
        $do_not_send = $this->input->post('do_not_send');
        if(!is_array($do_not_send)) $do_not_send = array();
       
        $schedule_type = $this->input->post('schedule_type');
        $schedule_time = $this->input->post('schedule_time');
        $time_zone = $this->input->post('time_zone');
        $lead_list = json_decode($this->input->post("info"),true);

        $delay_time = $this->input->post("delay_time");
        if($delay_time=="") $delay_time = 0;
        if($delay_time>15) $delay_time = 15;
        $unsubscribe_button = $this->input->post("unsubscribe_button");


        if($schedule_type == "now") $posting_status = "2";        
        else  $posting_status = "0";     

        $campaign_type= 'lead-wise';          
        $added_at = date("Y-m-d H:i:s");
        $is_spam_caught = "0";
        $successfully_sent = 0;
        $total_thread = 0;

        $page_ids = array();
        $fb_page_ids = array();
        $page_ids_names = array();
        $page_access_tokens = array();      
        $report = array();

        $send_to_array = array();
        foreach ($lead_list as $key => $value) 
        {
           if(!in_array($value['page_auto_id'], $page_ids))
           {
             $page_ids[] = $value['page_auto_id'];
             $fb_page_ids[] = $value['page_id'];
           }

           if(in_array($value['client_thread_id'], $do_not_send)) continue;

           if(isset($send_to_array[$value["client_id"]])) continue; // so that same user in different pages does not recieve same message again and again
           $send_to_array[$value["client_id"]] = 1;

           $total_thread++;

           // $get_page_auto_id = $page_id_association[$value['page_id']]; // page auto id to fb page id convsersion, facebook_rx_conversion_user_list dont have page auto id
           $report[$value['page_auto_id']][$value['client_thread_id']] = array
           (
            "client_username"=>$value["client_username"],
            "client_id"=>$value["client_id"],
            "message_sent_id"=>"Pending",
            "sent_time"=>"Pending",
            "page_name" => "",
            "lead_id" => $value["id"]
            );
        }

        $page_info = $this->basic->get_data("facebook_rx_fb_page_info",array("where_in"=>array("id"=>$page_ids)),array("page_name","id","page_access_token"));
        foreach ($page_info as $key => $value) 
        {
            $page_ids_names[$value['id']] = $value['page_name']; // page names stored to database to show in grid
            $page_access_tokens[$value['id']] = $value['page_access_token']; // page access tokens of selected pages
        }
        

        $campaign_message_db = $campaign_message;
     
        $data = array(
            'user_id' => $user_id,
            'page_ids' => implode(',',$page_ids), // comme seperated page auto id
            'fb_page_ids' => implode(',',$fb_page_ids), // comme seperated fb page id
            'page_ids_names' => json_encode($page_ids_names), //page auto id => page name associated array json
            'do_not_send_to' => json_encode($do_not_send), //exclude thread id array json
            'campaign_name' => $campaign_name,
            'campaign_type' => "lead-wise",
            'campaign_message' => $campaign_message_db,
            'schedule_time' => $schedule_time,
            'time_zone' => $time_zone,
            'posting_status' => $posting_status,
            'is_spam_caught' => $is_spam_caught,
            'total_thread' => $total_thread,
            'successfully_sent' => $successfully_sent,
            'attached_url'=>$link,
            'attached_video'=>$video_url,
            'added_at' => $added_at,
            'report' => json_encode($report), // page and thread array json
            'unsubscribe_button' => $unsubscribe_button,
            'delay_time' => $delay_time
        );

        //************************************************//
        $status=$this->_check_usage($module_id=79,$request=$total_thread); 
        if($status=="3")  //monthly limit is exceeded, can not send another ,message this month
        exit();
        //************************************************//

        $this->basic->insert_data('facebook_ex_conversation_campaign', $data); // at first campaign is insrted to database , then proccessed
        $campaign_id= $this->db->insert_id();

        //******************************//
        // insert data to useges log table
        $this->_insert_usage_log($module_id=76,$request=1);   
        //******************************//

        //******************************//
        // insert data to useges log table (message count)
        $this->_insert_usage_log($module_id=79,$request=$total_thread);   
        //******************************//
  
        
        //send now then start sending message 
        if($schedule_type == "now")
        {               
            $i=0;
            $send_report = array();
            $is_spam_caught_send = "0"; // is facebook marked this message as spam?
            $catch_error_count = 0; // catch block error count

            foreach($report as $key=>$value)
            {           
                if($catch_error_count>10) break;  // if 10 catch block error then stop sending

                $page_id_send  = $key;
                foreach ($value as $key2 => $value2) 
                {
                    if($catch_error_count>10) break; // if 10 catch block error then stop sending

                    $client_thread_id_send = $key2;
                    $client_id_send = $value2['client_id'];
                    $client_username_send = $value2['client_username'];
                    // added by mostofa at 04/03/2017 
                    $client_username_send_array = explode(' ', $client_username_send);
                    $client_last_name = array_pop($client_username_send_array);
                    $client_first_name = implode(' ', $client_username_send_array); 

                    $page_access_token_send = $page_access_tokens[$page_id_send];

                    //  generating message
                    $campaign_message_send = $campaign_message;
                    $campaign_message_send = str_replace('#LEAD_USER_NAME#',$client_username_send,$campaign_message_send);
                    // added by mostofa at 04/03/2017 
                    $campaign_message_send = str_replace('#LEAD_USER_FIRST_NAME#',$client_first_name,$campaign_message_send);
                    $campaign_message_send = str_replace('#LEAD_USER_LAST_NAME#',$client_last_name,$campaign_message_send);
                    
                    if($video_url!="") $campaign_message_send = $campaign_message_send."\n".$video_url;
                    else if($link!="") $campaign_message_send = $campaign_message_send."\n".$link;

                    // generate unsubscribe link
                    if($unsubscribe_button=="1")
                    {
                        $code = $this->_random_number_generator(6)."_".$value2["lead_id"]."_".$page_id_send."_".$this->_random_number_generator(6);
                        $code= base64_encode($code);
                        $code= urlencode($code);
                        $unsubscribe_link =site_url("home/ul/".$code);
                        $campaign_message_send = $campaign_message_send."\n\nUnsubscribe link : \n".$unsubscribe_link."\n";
                    }
                    

                    $error_msg="";
                    $message_error_code = "";
                    $message_sent_id = "";
                    try
                    {
                        $response = $this->fb_rx_login->send_message_to_thread($client_thread_id_send,$campaign_message_send,$page_access_token_send);
                        if(isset($response['id']))
                        {
                            $message_sent_id = $response['id']; 
                            $successfully_sent++; 
                        }
                        else 
                        {
                            if(isset($response["error"]["message"])) $message_sent_id = $response["error"]["message"];  
                            if(isset($response["error"]["code"])) $message_error_code = $response["error"]["code"]; 

                            if($message_error_code=="368") // if facebook marked message as spam 
                            {
                                $error_msg=$message_sent_id;
                                $is_spam_caught_send = "1";
                            }

                            if($message_error_code=="230") //user blocked page
                            {
                                $this->basic->update_data("facebook_rx_conversion_user_list",array("id"=>$value2["lead_id"]),array("permission"=>"0"));
                                if($this->db->affected_rows()>0)
                                {
                                    $this->basic->execute_complex_query("UPDATE facebook_rx_fb_page_info SET current_subscribed_lead_count=current_subscribed_lead_count-1,current_unsubscribed_lead_count=current_unsubscribed_lead_count+1 WHERE id='{$page_id_send}'");
                                }
                            }


                        } 

                        if($delay_time==0)
                        sleep(rand(3,12));
                        else sleep($delay_time);

                        
                    }

                    catch(Exception $e) 
                    {
                      $error_msg = $e->getMessage();
                      $catch_error_count++;
                    }
                    
                    // generating new report with send message info
                    $send_report[$page_id_send][$client_thread_id_send] = array
                    ( 
                        "client_username"=>$client_username_send,
                        "client_id"=>$client_id_send,
                        "message_sent_id"=> $message_sent_id,
                        "sent_time"=> date("Y-m-d H:i:s"),
                        "page_name" => $page_ids_names[$page_id_send]
                    ); 

                    $i++;  
                    // after 10 send update report in database
                    if($i%10==0)
                    {
                        $send_report_json= json_encode($send_report);
                        $this->basic->update_data("facebook_ex_conversation_campaign",array("id"=>$campaign_id),array("report"=>$send_report_json,'successfully_sent'=>$successfully_sent,"error_message"=>$error_msg));
                    }
                    
                    if($message_error_code=="368") break;  // if facebook marked message as spam , then stop sending
                                
                }

                if($message_error_code=="368") break; // if facebook marked message as spam , then stop sending

                
            }   

            // everything is done, now update database finally
            $send_report_json= json_encode($send_report);
            $this->basic->update_data("facebook_ex_conversation_campaign",array("id"=>$campaign_id),array("report"=>$send_report_json,"posting_status"=>'2','successfully_sent'=>$successfully_sent,'completed_at'=>date("Y-m-d H:i:s"),"is_spam_caught"=>$is_spam_caught_send,"error_message"=>$error_msg));

        } // end of now block

        
    }

    public function delete_campaign()
    {   
        if(!$_POST) exit();
        $id=$this->input->post("id");

        $xdata = $this->basic->get_data("facebook_ex_conversation_campaign",array("where"=>array("id"=>$id,"user_id"=>$this->user_id)),array("posting_status","total_thread"));

        $current_total_thread_abs  = isset($xdata[0]["total_thread"]) ? $xdata[0]["total_thread"] : 0;
        $posting_status  = isset($xdata[0]["posting_status"]) ? $xdata[0]["posting_status"] : "";

        if($posting_status=="0") // removing usage data if deleted and campaign is pending
        {
            $this->_delete_usage_log($module_id=76,$request=1); 
            if($current_total_thread_abs>0)  
            $this->_delete_usage_log($module_id=79,$request=$current_total_thread_abs);             
        }      

        if($this->basic->delete_data("facebook_ex_conversation_campaign",array("id"=>$id,"user_id"=>$this->user_id)))
        echo "1";
        else echo "0";
    }



    public function edit_custom_campaign($id=0)
    {
        if($id==0) exit();

        $data['body'] = "facebook_ex/campaign/edit_custom_campaign";
        $data['page_title'] = $this->lang->line("Edit custom campaign");
        $data["time_zone"]= $this->_time_zone_list();
        $data['emotion_list'] = $this->get_emotion_list();
        $data["xdata"] = $this->basic->get_data("facebook_ex_conversation_campaign",array("where"=>array("id"=>$id,"user_id"=>$this->user_id)));

        // only pending campaigns are editable
        if(!isset($data["xdata"][0]["posting_status"]) || $data["xdata"][0]["posting_status"]!='0' ) exit();

        $previous_exclude = isset($data["xdata"][0]["do_not_send_to"]) ? json_decode($data["xdata"][0]["do_not_send_to"]) : array();

        $data["xdo_not_send_to"]=array();
        if(count($previous_exclude)>0)
        $data["xdo_not_send_to"] = $this->basic->get_data("facebook_rx_conversion_user_list",array("where_in"=>array("client_thread_id"=>$previous_exclude,"user_id"=>$this->user_id)));
        
        $this->_viewcontroller($data);
    }

    public function edit_custom_campaign_action()
    {           
        if(!$_POST) exit();
        ignore_user_abort(TRUE); 

        $campaign_id = $this->input->post("campaign_id");
        
        $user_id = $this->user_id;
        $campaign_name = $this->input->post('campaign_name');
        $campaign_message = $this->input->post('message');
        $link = $this->input->post('link');
        $video_url = $this->input->post('video_url');
        
        $do_not_send = $this->input->post('do_not_send');
        if(!is_array($do_not_send)) $do_not_send = array();
       
        $schedule_time = $this->input->post('schedule_time');
        $time_zone = $this->input->post('time_zone');

        $delay_time = $this->input->post("delay_time");
        if($delay_time=="") $delay_time = 0;
        if($delay_time>15) $delay_time = 15;
        $unsubscribe_button = $this->input->post("unsubscribe_button");
        
        $data = array(
            'do_not_send_to' => json_encode($do_not_send), //exclude thread id array json
            'campaign_name' => $campaign_name,            
            'campaign_message' => $campaign_message,
            'schedule_time' => $schedule_time,
            'time_zone' => $time_zone,
            'attached_url'=>$link,
            'attached_video'=>$video_url,
            'delay_time' => $delay_time,
            'unsubscribe_button' => $unsubscribe_button
        );

        $this->basic->update_data('facebook_ex_conversation_campaign',array("id"=>$campaign_id,"user_id"=>$this->user_id),$data); // at first campaign is insrted to database , then proccessed
             
        
    }


   

}