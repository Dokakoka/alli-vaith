<?php


public function fes_form()
    {
        // Check if our nonce is set.
        if(!isset($_POST['_wpnonce'])) $this->main->response(array('success'=>0, 'code'=>'NONCE_MISSING'));

        // Verify that the nonce is valid.
        if(!wp_verify_nonce(sanitize_text_field($_POST['_wpnonce']), 'mec_fes_form')) $this->main->response(array('success'=>0, 'code'=>'NONCE_IS_INVALID'));

        $mec = isset($_POST['mec']) ? $this->main->sanitize_deep_array($_POST['mec']) : array();
        // Post ID
        $post_id = isset($mec['post_id']) ? (int) sanitize_text_field($mec['post_id']) : -1;

        // Show a warning to current user if modification of post is not possible for him/her
        if(!$this->current_user_can_upsert_event($post_id)) $this->main->response(array('success'=>0, 'message'=>esc_html__("Sorry! You don't have access to modify this event.", 'modern-events-calendar-lite'), 'code'=>'NO_ACCESS'));

        // Validate Captcha
        if($this->getCaptcha()->status('fes') and !$this->getCaptcha()->is_valid())
        {
            $this->main->response(array('success'=>0, 'message'=>__('Invalid Captcha! Please try again.', 'modern-events-calendar-lite'), 'code'=>'CAPTCHA_IS_INVALID'));
        }

        // Agreement Status
        $agreement_status = (isset($this->settings['fes_agreement']) and $this->settings['fes_agreement']);
        if($agreement_status)
        {
            $checked = (isset($mec['agreement']) and $mec['agreement']);
            if(!$checked) $this->main->response(array('success'=>0, 'message'=>__('You should accept the terms and conditions.', 'modern-events-calendar-lite'), 'code'=>'TERMS_CONDITIONS'));
        }

        $start_date = (isset($mec['date']['start']['date']) and trim($mec['date']['start']['date'])) ? $this->main->standardize_format(sanitize_text_field($mec['date']['start']['date'])) : date('Y-m-d');
        $end_date = (isset($mec['date']['end']['date']) and trim($mec['date']['end']['date'])) ? $this->main->standardize_format(sanitize_text_field($mec['date']['end']['date'])) : date('Y-m-d');

        $post_title = isset($mec['title']) ? sanitize_text_field($mec['title']) : '';
        $post_content = isset($mec['content']) ? MEC_kses::page($mec['content']) : '';
        $post_excerpt = isset($mec['excerpt']) ? MEC_kses::page($mec['excerpt']) : '';
        $post_tags = isset($mec['tags']) ? sanitize_text_field($mec['tags']) : '';
        $post_categories = isset($mec['categories']) ? array_map('sanitize_text_field', $mec['categories']) : array();
        $post_speakers = isset($mec['speakers']) ? array_map('sanitize_text_field', $mec['speakers']) : array();
        $post_sponsors = isset($mec['sponsors']) ? array_map('sanitize_text_field', $mec['sponsors']) : array();
        $post_labels = isset($mec['labels']) ? array_map('sanitize_text_field', $mec['labels']) : array();
        $featured_image = isset($mec['featured_image']) ? sanitize_text_field($mec['featured_image']) : '';

        $read_more = isset($mec['read_more']) ? sanitize_url($mec['read_more']) : '';
        $more_info = (isset($mec['more_info']) and trim($mec['more_info'])) ? sanitize_url($mec['more_info']) : '';
        $more_info_title = isset($mec['more_info_title']) ? sanitize_text_field($mec['more_info_title']) : '';
        $more_info_target = isset($mec['more_info_target']) ? sanitize_text_field($mec['more_info_target']) : '';

        $cost = isset($mec['cost']) ? sanitize_text_field($mec['cost']) : '';

        // Title is Required
        if(!trim($post_title)) $this->main->response(array('success'=>0, 'message'=>__('Please fill event title field!', 'modern-events-calendar-lite'), 'code'=>'TITLE_IS_EMPTY'));

        // Body is Required
        $is_required_content = apply_filters(
            'mec_fes_form_is_required_fields',
            isset($this->settings['fes_required_body']) and $this->settings['fes_required_body'] ? true : false,
            'content'
        );
        if($is_required_content && !trim($post_content)) $this->main->response(array('success'=>0, 'message'=>__('Please fill event body field!', 'modern-events-calendar-lite'), 'code'=>'BODY_IS_EMPTY'));

        // excerpt is Required
        $is_required_excerpt = apply_filters(
            'mec_fes_form_is_required_fields',
            isset($this->settings['fes_required_excerpt']) and $this->settings['fes_required_excerpt'] ? true : false,
            'excerpt'
        );
        if($is_required_excerpt && !trim($post_excerpt)) $this->main->response(array('success'=>0, 'message'=>__('Please fill event excerpt field!', 'modern-events-calendar-lite'), 'code'=>'EXCERPT_IS_EMPTY'));

        // Dates are Required
        $is_required_dates = apply_filters(
            'mec_fes_form_is_required_fields',
            (isset($this->settings['fes_required_dates']) and $this->settings['fes_required_dates']),
            'dates'
        );
        if($is_required_dates)
        {
            $start_date_is_filled = (isset($mec['date']['start']['date']) and trim($mec['date']['start']['date']));
            $end_date_is_filled = (isset($mec['date']['end']['date']) and trim($mec['date']['end']['date']));

            if(!$start_date_is_filled) $this->main->response(array('success'=>0, 'message'=>__('Please fill event start date!', 'modern-events-calendar-lite'), 'code'=>'START_DATE_IS_EMPTY'));
            if(!$end_date_is_filled) $this->main->response(array('success'=>0, 'message'=>__('Please fill event end date!', 'modern-events-calendar-lite'), 'code'=>'END_DATE_IS_EMPTY'));
        }

        // Category is Required
        $is_required_category = apply_filters(
            'mec_fes_form_is_required_fields',
            isset($this->settings['fes_section_categories']) and $this->settings['fes_section_categories'] and isset($this->settings['fes_required_category']) and $this->settings['fes_required_category'] ? true : false,
            'category'
        );
        if($is_required_category and is_array($post_categories) and !count($post_categories)) $this->main->response(array('success'=>0, 'message'=>__('Please select at-least one category!', 'modern-events-calendar-lite'), 'code'=>'CATEGORY_IS_EMPTY'));

        // Location is Required
        $is_required_location = apply_filters(
            'mec_fes_form_is_required_fields',
            (isset($this->settings['fes_section_location']) and $this->settings['fes_section_location'] and isset($this->settings['fes_required_location']) and $this->settings['fes_required_location']),
            'location'
        );
        if($is_required_location)
        {
            $location_id_is_filled = (isset($mec['location_id']) and trim($mec['location_id']) and $mec['location_id'] != 1);
            $location_add_request = (isset($mec['location'], $mec['location']['address']) and trim($mec['location']['address']));

            if(!$location_id_is_filled and !$location_add_request) $this->main->response(array('success'=>0, 'message'=>__('Please select the event location!', 'modern-events-calendar-lite'), 'code'=>'LOCATION_IS_EMPTY'));
        }

        // Label is Required
        $is_required_label = apply_filters(
            'mec_fes_form_is_required_fields',
            isset($this->settings['fes_section_labels']) and $this->settings['fes_section_labels'] and isset($this->settings['fes_required_label']) and $this->settings['fes_required_label'] and is_array($post_labels) ? true : false,
            'label'
        );
        if($is_required_label and !count($post_labels)) $this->main->response(array('success'=>0, 'message'=>__('Please select at-least one label!', 'modern-events-calendar-lite'), 'code'=>'LABEL_IS_EMPTY'));

        // Featured Image is Required
        $is_required_featured_image = apply_filters(
            'mec_fes_form_is_required_fields',
            isset($this->settings['fes_section_featured_image']) and $this->settings['fes_section_featured_image'] and isset($this->settings['fes_required_featured_image']) and $this->settings['fes_required_featured_image'] ? true : false,
            'featured_image'
        );
        if($is_required_featured_image and !trim($featured_image)) $this->main->response(array('success'=>0, 'message'=>__('Please upload a featured image!', 'modern-events-calendar-lite'), 'code'=>'FEATURED_IMAGE_IS_EMPTY'));

        // Event link is required
        $is_required_event_link = apply_filters(
            'mec_fes_form_is_required_fields',
            isset($this->settings['fes_required_event_link']) and $this->settings['fes_required_event_link'] and isset($this->settings['fes_required_event_link']) and $this->settings['fes_required_event_link'] ? true : false,
            'event_link'
        );
        if($is_required_event_link and !trim($read_more)) $this->main->response(array('success'=>0, 'message'=>__('Please fill event link!', 'modern-events-calendar-lite'), 'code'=>'EVENT_LINK_IS_EMPTY'));

        // More Info is required
        $is_required_more_info = apply_filters(
            'mec_fes_form_is_required_fields',
            isset($this->settings['fes_required_more_info']) and $this->settings['fes_required_more_info'] and isset($this->settings['fes_required_more_info']) and $this->settings['fes_required_more_info'] ? true : false,
            'more_info'
        );
        if($is_required_more_info and !trim($more_info)) $this->main->response(array('success'=>0, 'message'=>__('Please fill more info!', 'modern-events-calendar-lite'), 'code'=>'MORE_INFO_IS_EMPTY'));

        // Cost is required
        $is_required_cost = apply_filters(
            'mec_fes_form_is_required_fields',
            isset($this->settings['fes_required_cost']) and $this->settings['fes_required_cost'] and isset($this->settings['fes_required_cost']) and $this->settings['fes_required_cost'] ? true : false,
            'cost'
        );
        if($is_required_cost and trim($cost) === '') $this->main->response(array('success'=>0, 'message'=>__('Please fill cost!', 'modern-events-calendar-lite'), 'code'=>'COST_IS_EMPTY'));

        // Post Status
        $status = 'pending';
        if(current_user_can('publish_posts')) $status = 'publish';

        $method = 'updated';

        // Create new event
        if($post_id == -1)
        {
            // Force Status
            if(isset($this->settings['fes_new_event_status']) and trim($this->settings['fes_new_event_status'])) $status = $this->settings['fes_new_event_status'];

            $post = array('post_title'=>$post_title, 'post_content'=>$post_content, 'post_excerpt'=>$post_excerpt, 'post_type'=>$this->PT, 'post_status'=>$status);
            $post_id = wp_insert_post($post);

            $method = 'added';

            // FES Flag
            update_post_meta($post_id, 'mec_created_by_fes', 1);

            // Default Category
            if(isset($this->settings['fes_default_category']) and $this->settings['fes_default_category'] and !count($post_categories))
            {
                $post_categories[$this->settings['fes_default_category']] = 1;
            }
        }

        wp_update_post(array('ID'=>$post_id, 'post_title'=>$post_title, 'post_content'=>$post_content, 'post_excerpt'=>$post_excerpt,));

        // Categories Section
        if(!isset($this->settings['fes_section_categories']) or (isset($this->settings['fes_section_categories']) and $this->settings['fes_section_categories']))
        {
            // Categories
            $categories = array();
            foreach($post_categories as $post_category=>$value) $categories[] = (int) $post_category;

            wp_set_post_terms($post_id, $categories, 'mec_category');
        }

        // Speakers Section
        if(!isset($this->settings['fes_section_speaker']) or (isset($this->settings['fes_section_speaker']) and $this->settings['fes_section_speaker']))
        {
            // Speakers
            if(isset($this->settings['speakers_status']) and $this->settings['speakers_status'])
            {
                $speakers = array();
                foreach($post_speakers as $post_speaker=>$value) $speakers[] = (int) $post_speaker;

                wp_set_post_terms($post_id, $speakers, 'mec_speaker');
            }
        }

        // Sponsors Section
        if($this->getPRO() and isset($this->settings['fes_section_sponsor']) and $this->settings['fes_section_sponsor'])
        {
            // Sponsors
            if(isset($this->settings['sponsors_status']) and $this->settings['sponsors_status'])
            {
                $sponsors = array();
                foreach($post_sponsors as $post_sponsor=>$value) $sponsors[] = (int) $post_sponsor;

                wp_set_post_terms($post_id, $sponsors, 'mec_sponsor');
            }
        }

        // Labels Section
        if(!isset($this->settings['fes_section_labels']) or (isset($this->settings['fes_section_labels']) and $this->settings['fes_section_labels']))
        {
            // Labels
            $labels = array();
            foreach($post_labels as $post_label=>$value) $labels[] = (int) $post_label;

            wp_set_post_terms($post_id, $labels, 'mec_label');
            do_action('mec_label_change_to_radio', $labels, $post_labels, $post_id);
        }

        // Color Section
        if(!isset($this->settings['fes_section_event_color']) or (isset($this->settings['fes_section_event_color']) and $this->settings['fes_section_event_color']))
        {
            // Color
            $color = isset($mec['color']) ? sanitize_text_field(trim($mec['color'], '# ')) : '';
            update_post_meta($post_id, 'mec_color', $color);
        }

        // Tags Section
        if(!isset($this->settings['fes_section_tags']) or (isset($this->settings['fes_section_tags']) and $this->settings['fes_section_tags']))
        {
            // Tags
            wp_set_post_terms($post_id, $post_tags, apply_filters('mec_taxonomy_tag', ''));
        }

        // Featured Image Section
        if(!isset($this->settings['fes_section_featured_image']) or (isset($this->settings['fes_section_featured_image']) and $this->settings['fes_section_featured_image']))
        {
            // Featured Image
            if(trim($featured_image)) $this->main->set_featured_image($featured_image, $post_id);
            else delete_post_thumbnail($post_id);

            // Featured Image Caption
            if(isset($this->settings['featured_image_caption']) and $this->settings['featured_image_caption'])
            {
                $attachment_id = get_post_thumbnail_id($post_id);
                if($attachment_id)
                {
                    $featured_image_caption = isset($mec['featured_image_caption']) ? sanitize_text_field($mec['featured_image_caption']) : '';
                    $this->db->q("UPDATE `#__posts` SET `post_excerpt`='".esc_sql($featured_image_caption)."' WHERE `ID`=".((int) $attachment_id));
                }
            }
        }

        // Links Section
        if(!isset($this->settings['fes_section_event_links']) or (isset($this->settings['fes_section_event_links']) and $this->settings['fes_section_event_links']))
        {
            update_post_meta($post_id, 'mec_read_more', $read_more);
            update_post_meta($post_id, 'mec_more_info', $more_info);
            update_post_meta($post_id, 'mec_more_info_title', $more_info_title);
            update_post_meta($post_id, 'mec_more_info_target', $more_info_target);
        }

        // Cost Section
        if(!isset($this->settings['fes_section_cost']) or (isset($this->settings['fes_section_cost']) and $this->settings['fes_section_cost']))
        {
            $cost = apply_filters(
                'mec_event_cost_sanitize',
                sanitize_text_field($cost),
                $cost
            );

            $cost_auto_calculate = (isset($mec['cost_auto_calculate']) ? sanitize_text_field($mec['cost_auto_calculate']) : 0);
            $currency_options = ((isset($mec['currency']) and is_array($mec['currency'])) ? array_map('sanitize_text_field', $mec['currency']) : array());

            update_post_meta($post_id, 'mec_cost', $cost);
            update_post_meta($post_id, 'mec_cost_auto_calculate', $cost_auto_calculate);
            update_post_meta($post_id, 'mec_currency', $currency_options);
        }

        // Guest Name and Email
        $fes_guest_email = isset($mec['fes_guest_email']) ? sanitize_email($mec['fes_guest_email']) : '';
        $fes_guest_name = isset($mec['fes_guest_name']) ? sanitize_text_field($mec['fes_guest_name']) : '';
        $note = isset($mec['note']) ? sanitize_text_field($mec['note']) : '';

        update_post_meta($post_id, 'fes_guest_email', $fes_guest_email);
        update_post_meta($post_id, 'fes_guest_name', $fes_guest_name);
        update_post_meta($post_id, 'mec_note', $note);

        // Location Section
        if(!isset($this->settings['fes_section_location']) or (isset($this->settings['fes_section_location']) and $this->settings['fes_section_location']))
        {
            // Location
            $location_id = isset($mec['location_id']) ? sanitize_text_field($mec['location_id']) : 1;

                
            // Selected a saved location
            if($location_id)
            {
                // Set term to the post
                wp_set_object_terms($post_id, (int) $location_id, 'mec_location');
            }
            else
            {
                $address = (isset($mec['location']['address']) and trim($mec['location']['address'])) ? sanitize_text_field($mec['location']['address']) : '';
                $name = (isset($mec['location']['name']) and trim($mec['location']['name'])) ? sanitize_text_field($mec['location']['name']) : (trim($address) ? $address : esc_html__('Location Name', 'modern-events-calendar-lite'));

                $term = get_term_by('name', $name, 'mec_location');

                // Term already exists
                if(is_object($term) and isset($term->term_id))
                {
                    // Set term to the post
                    $location_id = (int) $term->term_id;
                    wp_set_object_terms($post_id, (int) $term->term_id, 'mec_location');
                }
                else
                {
                    $term = wp_insert_term($name, 'mec_location');

                    $location_id = $term['term_id'];

                    if($location_id)
                    {
                        // Set term to the post
                        wp_set_object_terms($post_id, (int) $location_id, 'mec_location');

                        $opening_hour = (isset($mec['location']['opening_hour']) and trim($mec['location']['opening_hour'])) ? sanitize_text_field($mec['location']['opening_hour']) : '';
                        $latitude = (isset($mec['location']['latitude']) and trim($mec['location']['latitude'])) ? sanitize_text_field($mec['location']['latitude']) : 0;
                        $longitude = (isset($mec['location']['longitude']) and trim($mec['location']['longitude'])) ? sanitize_text_field($mec['location']['longitude']) : 0;
                        $url = (isset($mec['location']['url']) and trim($mec['location']['url'])) ? sanitize_url($mec['location']['url']) : '';
                        $thumbnail = (isset($mec['location']['thumbnail']) and trim($mec['location']['thumbnail'])) ? sanitize_text_field($mec['location']['thumbnail']) : '';

                        if(!trim($latitude) or !trim($longitude))
                        {
                            $geo_point = $this->main->get_lat_lng($address);

                            $latitude = $geo_point[0];
                            $longitude = $geo_point[1];
                        }

                        update_term_meta($location_id, 'address', $address);
                        update_term_meta($location_id, 'opening_hour', $opening_hour);
                        update_term_meta($location_id, 'latitude', $latitude);
                        update_term_meta($location_id, 'longitude', $longitude);
                        update_term_meta($location_id, 'url', $url);
                        update_term_meta($location_id, 'thumbnail', $thumbnail);
                    }
                    else $location_id = 1;
                }
            }
            update_post_meta($post_id, 'mec_location_id', $location_id);

            $dont_show_map = isset($mec['dont_show_map']) ? sanitize_text_field($mec['dont_show_map']) : 0;
            update_post_meta($post_id, 'mec_dont_show_map', $dont_show_map);
        }

        // Organizer Section
        if(!isset($this->settings['fes_section_organizer']) or (isset($this->settings['fes_section_organizer']) and $this->settings['fes_section_organizer']))
        {
            // Organizer
            $organizer_id = isset($mec['organizer_id']) ? sanitize_text_field($mec['organizer_id']) : 1;

            // Selected a saved organizer
            if(isset($organizer_id) and $organizer_id)
            {
                // Set term to the post
                wp_set_object_terms($post_id, (int) $organizer_id, 'mec_organizer');
            }
            else
            {
                $name = (isset($mec['organizer']['name']) and trim($mec['organizer']['name'])) ? sanitize_text_field($mec['organizer']['name']) : esc_html__('Organizer Name', 'modern-events-calendar-lite');

                $term = get_term_by('name', $name, 'mec_organizer');

                // Term already exists
                if(is_object($term) and isset($term->term_id))
                {
                    // Set term to the post
					$organizer_id = (int) $term->term_id;       
                    wp_set_object_terms($post_id, (int) $term->term_id, 'mec_organizer');
                }
                else
                {
                    $term = wp_insert_term($name, 'mec_organizer');

                    $organizer_id = $term['term_id'];
                    if($organizer_id)
                    {
                        // Set term to the post
                        wp_set_object_terms($post_id, (int) $organizer_id, 'mec_organizer');

                        $tel = (isset($mec['organizer']['tel']) and trim($mec['organizer']['tel'])) ? sanitize_text_field($mec['organizer']['tel']) : '';
                        $email = (isset($mec['organizer']['email']) and trim($mec['organizer']['email'])) ? sanitize_text_field($mec['organizer']['email']) : '';
                        $url = (isset($mec['organizer']['url']) and trim($mec['organizer']['url'])) ? sanitize_url($mec['organizer']['url']) : '';
                        $page_label = (isset($mec['organizer']['page_label']) and trim($mec['organizer']['page_label'])) ? sanitize_text_field($mec['organizer']['page_label']) : '';
                        $thumbnail = (isset($mec['organizer']['thumbnail']) and trim($mec['organizer']['thumbnail'])) ? sanitize_text_field($mec['organizer']['thumbnail']) : '';

                        update_term_meta($organizer_id, 'tel', $tel);
                        update_term_meta($organizer_id, 'email', $email);
                        update_term_meta($organizer_id, 'url', $url);
                        update_term_meta($organizer_id, 'page_label', $page_label);
                        update_term_meta($organizer_id, 'thumbnail', $thumbnail);
                    }
                    else $organizer_id = 1;
                }
            }

            update_post_meta($post_id, 'mec_organizer_id', $organizer_id);

            // Additional Organizers
            $additional_organizer_ids = isset($mec['additional_organizer_ids']) ? $mec['additional_organizer_ids'] : array();

            foreach($additional_organizer_ids as $additional_organizer_id) wp_set_object_terms($post_id, (int) $additional_organizer_id, 'mec_organizer', true);
            update_post_meta($post_id, 'mec_additional_organizer_ids', $additional_organizer_ids);

            // Additional locations
            $additional_location_ids = isset($mec['additional_location_ids']) ? $mec['additional_location_ids'] : array();

            foreach($additional_location_ids as $additional_location_id) wp_set_object_terms($post_id, (int) $additional_location_id, 'mec_location', true);
            update_post_meta($post_id, 'mec_additional_location_ids', $additional_location_ids);
        }

        // Date Options
        $date = isset($mec['date']) ? $mec['date'] : array();

        $start_date = date('Y-m-d', strtotime($start_date));

        // Set the start date
        $date['start']['date'] = $start_date;

        $start_time_hour = isset($date['start']) ? sanitize_text_field($date['start']['hour']) : '8';
        $start_time_minutes = isset($date['start']) ? sanitize_text_field($date['start']['minutes']) : '00';
        $start_time_ampm = (isset($date['start']) and isset($date['start']['ampm'])) ? sanitize_text_field($date['start']['ampm']) : 'AM';

        $end_date = date('Y-m-d', strtotime($end_date));

        // Fix end_date if it's smaller than start_date
        if(strtotime($end_date) < strtotime($start_date)) $end_date = $start_date;

        // Set the end date
        $date['end']['date'] = $end_date;

        $end_time_hour = isset($date['end']) ? sanitize_text_field($date['end']['hour']) : '6';
        $end_time_minutes = isset($date['end']) ? sanitize_text_field($date['end']['minutes']) : '00';
        $end_time_ampm = (isset($date['end']) and isset($date['end']['ampm'])) ? sanitize_text_field($date['end']['ampm']) : 'PM';

        if(isset($this->settings['time_format']) and $this->settings['time_format'] == 24)
        {
            $day_start_seconds = $this->main->time_to_seconds($this->main->to_24hours($start_time_hour, NULL), $start_time_minutes);
            $day_end_seconds = $this->main->time_to_seconds($this->main->to_24hours($end_time_hour, NULL), $end_time_minutes);
        }
        else
        {
            $day_start_seconds = $this->main->time_to_seconds($this->main->to_24hours($start_time_hour, $start_time_ampm), $start_time_minutes);
            $day_end_seconds = $this->main->time_to_seconds($this->main->to_24hours($end_time_hour, $end_time_ampm), $end_time_minutes);
        }

        if($end_date === $start_date and $day_end_seconds < $day_start_seconds)
        {
            $day_end_seconds = $day_start_seconds;

            $end_time_hour = $start_time_hour;
            $end_time_minutes = $start_time_minutes;
            $end_time_ampm = $start_time_ampm;

            $date['end']['hour'] = $start_time_hour;
            $date['end']['minutes'] = $start_time_minutes;
            $date['end']['ampm'] = $start_time_ampm;
        }

        // If 24 hours format is enabled then convert it back to 12 hours
        if(isset($this->settings['time_format']) and $this->settings['time_format'] == 24)
        {
            if($start_time_hour < 12) $start_time_ampm = 'AM';
            elseif($start_time_hour == 12) $start_time_ampm = 'PM';
            elseif($start_time_hour > 12)
            {
                $start_time_hour -= 12;
                $start_time_ampm = 'PM';
            }
            elseif($start_time_hour == 0)
            {
                $start_time_hour = 12;
                $start_time_ampm = 'AM';
            }

            if($end_time_hour < 12) $end_time_ampm = 'AM';
            elseif($end_time_hour == 12) $end_time_ampm = 'PM';
            elseif($end_time_hour > 12)
            {
                $end_time_hour -= 12;
                $end_time_ampm = 'PM';
            }
            elseif($end_time_hour == 0)
            {
                $end_time_hour = 12;
                $end_time_ampm = 'AM';
            }

            // Set converted values to date array
            $date['start']['hour'] = $start_time_hour;
            $date['start']['ampm'] = $start_time_ampm;

            $date['end']['hour'] = $end_time_hour;
            $date['end']['ampm'] = $end_time_ampm;
        }

        $allday = isset($date['allday']) ? 1 : 0;
        $one_occurrence = isset($date['one_occurrence']) ? 1 : 0;
        $hide_time = isset($date['hide_time']) ? 1 : 0;
        $hide_end_time = isset($date['hide_end_time']) ? 1 : 0;
        $comment = isset($date['comment']) ? sanitize_text_field($date['comment']) : '';
        $timezone = (isset($mec['timezone']) and trim($mec['timezone']) != '') ? sanitize_text_field($mec['timezone']) : 'global';
        $countdown_method = (isset($mec['countdown_method']) and trim($mec['countdown_method']) != '') ? sanitize_text_field($mec['countdown_method']) : 'global';
        $style_per_event = (isset($mec['style_per_event']) and trim($mec['style_per_event']) != '') ? sanitize_text_field($mec['style_per_event']) : 'global';
        $trailer_url = (isset($mec['trailer_url']) and trim($mec['trailer_url']) != '') ? sanitize_url($mec['trailer_url']) : '';
        $trailer_title = isset($mec['trailer_title']) ? sanitize_text_field($mec['trailer_title']) : '';
        $public = (isset($mec['public']) and trim($mec['public']) != '') ? sanitize_text_field($mec['public']) : 1;

        // Set start time and end time if event is all day
        if($allday == 1)
        {
            $start_time_hour = '8';
            $start_time_minutes = '00';
            $start_time_ampm = 'AM';

            $end_time_hour = '6';
            $end_time_minutes = '00';
            $end_time_ampm = 'PM';
        }

        // Previous Date Times
        $prev_start_datetime = get_post_meta($post_id, 'mec_start_datetime', true);
        $prev_end_datetime = get_post_meta($post_id, 'mec_end_datetime', true);

        $start_datetime = $start_date.' '.sprintf('%02d', $start_time_hour).':'.sprintf('%02d', $start_time_minutes).' '.$start_time_ampm;
        $end_datetime = $end_date.' '.sprintf('%02d', $end_time_hour).':'.sprintf('%02d', $end_time_minutes).' '.$end_time_ampm;

        update_post_meta($post_id, 'mec_start_date', $start_date);
        update_post_meta($post_id, 'mec_start_time_hour', $start_time_hour);
        update_post_meta($post_id, 'mec_start_time_minutes', $start_time_minutes);
        update_post_meta($post_id, 'mec_start_time_ampm', $start_time_ampm);
        update_post_meta($post_id, 'mec_start_day_seconds', $day_start_seconds);
        update_post_meta($post_id, 'mec_start_datetime', $start_datetime);

        update_post_meta($post_id, 'mec_end_date', $end_date);
        update_post_meta($post_id, 'mec_end_time_hour', $end_time_hour);
        update_post_meta($post_id, 'mec_end_time_minutes', $end_time_minutes);
        update_post_meta($post_id, 'mec_end_time_ampm', $end_time_ampm);
        update_post_meta($post_id, 'mec_end_day_seconds', $day_end_seconds);
        update_post_meta($post_id, 'mec_end_datetime', $end_datetime);

        update_post_meta($post_id, 'mec_date', $date);

        // Repeat Options
        $repeat = isset($date['repeat']) ? $date['repeat'] : array();
        $certain_weekdays = isset($repeat['certain_weekdays']) ? $repeat['certain_weekdays'] : array();

        $repeat_status = isset($repeat['status']) ? 1 : 0;
        $repeat_type = ($repeat_status and isset($repeat['type'])) ? sanitize_text_field($repeat['type']) : '';

        $repeat_interval = ($repeat_status and isset($repeat['interval']) and trim($repeat['interval'])) ? sanitize_text_field($repeat['interval']) : 1;

        // Advanced Repeat
        $advanced = isset($repeat['advanced']) ? sanitize_text_field($repeat['advanced']) : '';

        if(!is_numeric($repeat_interval)) $repeat_interval = NULL;

        if($repeat_type == 'weekly') $interval_multiply = 7;
        else $interval_multiply = 1;

        // Reset certain weekdays if repeat type is not set to certain weekdays
        if($repeat_type != 'certain_weekdays') $certain_weekdays = array();

        if(!is_null($repeat_interval)) $repeat_interval = $repeat_interval*$interval_multiply;

        // String To Array
		if($repeat_type == 'advanced' and trim($advanced)) $advanced = explode('-', $advanced);
        else $advanced = array();

        $repeat_end = ($repeat_status and isset($repeat['end'])) ? sanitize_text_field($repeat['end']) : '';
        $repeat_end_at_occurrences = ($repeat_status && isset($repeat['end_at_occurrences']) && is_numeric($repeat['end_at_occurrences'])) ? $repeat['end_at_occurrences'] - 1 : 9;
        $repeat_end_at_date = ($repeat_status and isset($repeat['end_at_date'])) ? $this->main->standardize_format(sanitize_text_field($repeat['end_at_date'])) : '';

        update_post_meta($post_id, 'mec_date', $date);
        update_post_meta($post_id, 'mec_repeat', $repeat);
        update_post_meta($post_id, 'mec_certain_weekdays', $certain_weekdays);
        update_post_meta($post_id, 'mec_allday', $allday);
        update_post_meta($post_id, 'one_occurrence', $one_occurrence);
        update_post_meta($post_id, 'mec_hide_time', $hide_time);
        update_post_meta($post_id, 'mec_hide_end_time', $hide_end_time);
        update_post_meta($post_id, 'mec_comment', $comment);
        update_post_meta($post_id, 'mec_timezone', $timezone);
        update_post_meta($post_id, 'mec_countdown_method', $countdown_method);
        update_post_meta($post_id, 'mec_style_per_event', $style_per_event);
        update_post_meta($post_id, 'mec_trailer_url', $trailer_url);
        update_post_meta($post_id, 'mec_trailer_title', $trailer_title);
        update_post_meta($post_id, 'mec_public', $public);
        update_post_meta($post_id, 'mec_repeat_status', $repeat_status);
        update_post_meta($post_id, 'mec_repeat_type', $repeat_type);
        update_post_meta($post_id, 'mec_repeat_interval', $repeat_interval);
        update_post_meta($post_id, 'mec_repeat_end', $repeat_end);
        update_post_meta($post_id, 'mec_repeat_end_at_occurrences', $repeat_end_at_occurrences);
        update_post_meta($post_id, 'mec_repeat_end_at_date', $repeat_end_at_date);
        update_post_meta($post_id, 'mec_advanced_days', $advanced);

        // Event Sequence (Used in iCal feed)
        $sequence = (int) get_post_meta($post_id, 'mec_sequence', true);
        update_post_meta($post_id, 'mec_sequence', ($sequence + 1));

        // Creating $event array for inserting in mec_events table
        $event = array('post_id'=>$post_id, 'start'=>$start_date, 'repeat'=>$repeat_status, 'rinterval'=>(!in_array($repeat_type, array('daily', 'weekly', 'monthly')) ? NULL : $repeat_interval), 'time_start'=>$day_start_seconds, 'time_end'=>$day_end_seconds);

        $year = NULL;
        $month = NULL;
        $day = NULL;
        $week = NULL;
        $weekday = NULL;
        $weekdays = NULL;

        // MEC weekdays
        $mec_weekdays = $this->main->get_weekdays();

        // MEC weekends
        $mec_weekends = $this->main->get_weekends();

        $plus_date = null;
        if($repeat_type == 'daily')
        {
            $plus_date = '+'.$repeat_end_at_occurrences*$repeat_interval.' Days';
        }
        elseif($repeat_type == 'weekly')
        {
            $plus_date = '+'.$repeat_end_at_occurrences*($repeat_interval).' Days';
        }
        elseif($repeat_type == 'weekday')
        {
            $repeat_interval = 1;
            $plus_date = '+'.$repeat_end_at_occurrences*$repeat_interval.' Weekdays';

            $weekdays = ','.implode(',', $mec_weekdays).',';
        }
        elseif($repeat_type == 'weekend')
        {
            $repeat_interval = 1;
            $plus_date = '+'.round($repeat_end_at_occurrences/2)*($repeat_interval*7).' Days';

            $weekdays = ','.implode(',', $mec_weekends).',';
        }
        elseif($repeat_type == 'certain_weekdays')
        {
            $repeat_interval = 1;
            $plus_date = '+' . ceil(($repeat_end_at_occurrences * $repeat_interval) * (7/count($certain_weekdays))) . ' days';

            $weekdays = ','.implode(',', $certain_weekdays).',';
        }
        elseif($repeat_type == 'monthly')
        {
            $plus_date = '+'.$repeat_end_at_occurrences*$repeat_interval.' Months';

            $year = '*';
            $month = '*';

            $s = $start_date;
            $e = $end_date;

            $_days = array();
            while(strtotime($s) <= strtotime($e))
            {
                $_days[] = date('d', strtotime($s));
                $s = date('Y-m-d', strtotime('+1 Day', strtotime($s)));
            }

            $day = ','.implode(',', array_unique($_days)).',';

            $week = '*';
            $weekday = '*';
        }
        elseif($repeat_type == 'yearly')
        {
            $plus_date = '+'.$repeat_end_at_occurrences*$repeat_interval.' Years';

            $year = '*';

            $s = $start_date;
            $e = $end_date;

            $_months = array();
            $_days = array();
            while(strtotime($s) <= strtotime($e))
            {
                $_months[] = date('m', strtotime($s));
                $_days[] = date('d', strtotime($s));

                $s = date('Y-m-d', strtotime('+1 Day', strtotime($s)));
            }

            $month = ','.implode(',', array_unique($_months)).',';
            $day = ','.implode(',', array_unique($_days)).',';

            $week = '*';
            $weekday = '*';
        }
        elseif($repeat_type == "advanced")
        {
            // Render class object
            $this->render = $this->getRender();

            // Get finish date
            $event_info = array('start' => $date['start'], 'end' => $date['end']);
            $dates = $this->render->generate_advanced_days($advanced, $event_info, $repeat_end_at_occurrences +1, date( 'Y-m-d', current_time( 'timestamp', 0 )), 'events');

            $period_date = $this->main->date_diff($start_date, end($dates)['end']['date']);

            $plus_date = '+' . $period_date->days . ' Days';
        }

        // "In Days" and "Not In Days"
        $in_days_arr = (isset($mec['in_days']) and is_array($mec['in_days']) and count($mec['in_days'])) ? array_unique($mec['in_days']) : array();
        $not_in_days_arr = (isset($mec['not_in_days']) and is_array($mec['not_in_days']) and count($mec['not_in_days'])) ? array_unique($mec['not_in_days']) : array();

        $in_days = '';
        if(count($in_days_arr))
        {
            if(isset($in_days_arr[':i:'])) unset($in_days_arr[':i:']);

            $in_days_arr = array_map(function($value)
            {
                $ex = explode(':', $value);

                $in_days_times = '';
                if(isset($ex[2]) and isset($ex[3]))
                {
                    $in_days_start_time = $ex[2];
                    $in_days_end_time = $ex[3];

                    // If 24 hours format is enabled then convert it back to 12 hours
                    if(isset($this->settings['time_format']) and $this->settings['time_format'] == 24)
                    {
                        $ex_start_time = explode('-', $in_days_start_time);
                        $ex_end_time = explode('-', $in_days_end_time);

                        $in_days_start_hour = $ex_start_time[0];
                        $in_days_start_minutes = $ex_start_time[1];
                        $in_days_start_ampm = $ex_start_time[2];

                        $in_days_end_hour = $ex_end_time[0];
                        $in_days_end_minutes = $ex_end_time[1];
                        $in_days_end_ampm = $ex_end_time[2];

                        if(trim($in_days_start_ampm) == '')
                        {
                            if($in_days_start_hour < 12) $in_days_start_ampm = 'AM';
                            elseif($in_days_start_hour == 12) $in_days_start_ampm = 'PM';
                            elseif($in_days_start_hour > 12)
                            {
                                $in_days_start_hour -= 12;
                                $in_days_start_ampm = 'PM';
                            }
                            elseif($in_days_start_hour == 0)
                            {
                                $in_days_start_hour = 12;
                                $in_days_start_ampm = 'AM';
                            }
                        }

                        if(trim($in_days_end_ampm) == '')
                        {
                            if($in_days_end_hour < 12) $in_days_end_ampm = 'AM';
                            elseif($in_days_end_hour == 12) $in_days_end_ampm = 'PM';
                            elseif($in_days_end_hour > 12)
                            {
                                $in_days_end_hour -= 12;
                                $in_days_end_ampm = 'PM';
                            }
                            elseif($in_days_end_hour == 0)
                            {
                                $in_days_end_hour = 12;
                                $in_days_end_ampm = 'AM';
                            }
                        }

                        if(strlen($in_days_start_hour) == 1) $in_days_start_hour = '0'.$in_days_start_hour;
                        if(strlen($in_days_start_minutes) == 1) $in_days_start_minutes = '0'.$in_days_start_minutes;

                        if(strlen($in_days_end_hour) == 1) $in_days_end_hour = '0'.$in_days_end_hour;
                        if(strlen($in_days_end_minutes) == 1) $in_days_end_minutes = '0'.$in_days_end_minutes;

                        $in_days_start_time = $in_days_start_hour.'-'.$in_days_start_minutes.'-'.$in_days_start_ampm;
                        $in_days_end_time = $in_days_end_hour.'-'.$in_days_end_minutes.'-'.$in_days_end_ampm;
                    }

                    $in_days_times = ':'.$in_days_start_time.':'.$in_days_end_time;
                }

                return $this->main->standardize_format($ex[0]) . ':' . $this->main->standardize_format($ex[1]).$in_days_times;
            }, $in_days_arr);

            usort($in_days_arr, function($a, $b)
            {
                $ex_a = explode(':', $a);
                $ex_b = explode(':', $b);

                $date_a = $ex_a[0];
                $date_b = $ex_b[0];

                $in_day_a_time_label = '';
                if(isset($ex_a[2]))
                {
                    $in_day_a_time = $ex_a[2];
                    $pos = strpos($in_day_a_time, '-');
                    if($pos !== false) $in_day_a_time_label = substr_replace($in_day_a_time, ':', $pos, 1);

                    $in_day_a_time_label = str_replace('-', ' ', $in_day_a_time_label);
                }

                $in_day_b_time_label = '';
                if(isset($ex_b[2]))
                {
                    $in_day_b_time = $ex_b[2];
                    $pos = strpos($in_day_b_time, '-');
                    if($pos !== false) $in_day_b_time_label = substr_replace($in_day_b_time, ':', $pos, 1);

                    $in_day_b_time_label = str_replace('-', ' ', $in_day_b_time_label);
                }

                return strtotime(trim($date_a.' '.$in_day_a_time_label)) - strtotime(trim($date_b.' '.$in_day_b_time_label));
            });

            if(!isset($in_days_arr[':i:'])) $in_days_arr[':i:'] = ':val:';
            foreach($in_days_arr as $key => $in_day_arr)
            {
                if(is_numeric($key)) $in_days .= $in_day_arr . ',';
            }
        }

        $not_in_days = '';
        if(count($not_in_days_arr))
        {
            foreach($not_in_days_arr as $key => $not_in_day_arr)
            {
                if(is_numeric($key)) $not_in_days .= $this->main->standardize_format($not_in_day_arr) . ',';
            }
        }

        $in_days = trim($in_days, ', ');
        $not_in_days = trim($not_in_days, ', ');

        update_post_meta($post_id, 'mec_in_days', $in_days);
        update_post_meta($post_id, 'mec_not_in_days', $not_in_days);

        // Repeat End Date
        if($repeat_end == 'never') $repeat_end_date = '0000-00-00';
        elseif($repeat_end == 'date') $repeat_end_date = $repeat_end_at_date;
        elseif($repeat_end == 'occurrences')
        {
            if($plus_date) $repeat_end_date = date('Y-m-d', strtotime($plus_date, strtotime($end_date)));
            else $repeat_end_date = '0000-00-00';
        }
        else $repeat_end_date = '0000-00-00';

        // If event is not repeating then set the end date of event correctly
        if(!$repeat_status or $repeat_type == 'custom_days') $repeat_end_date = $end_date;

        // Add parameters to the $event
        $event['end'] = $repeat_end_date;
        $event['year'] = $year;
        $event['month'] = $month;
        $event['day'] = $day;
        $event['week'] = $week;
        $event['weekday'] = $weekday;
        $event['weekdays'] = $weekdays;
        $event['days'] = $in_days;
        $event['not_in_days'] = $not_in_days;

        // Update MEC Events Table
        $mec_event_id = $this->db->select($this->db->prepare("SELECT `id` FROM `#__mec_events` WHERE `post_id` = %d", $post_id), 'loadResult');

        if(!$mec_event_id)
        {
            $q1 = "";
            $q2 = "";

            foreach($event as $key=>$value)
            {
                $q1 .= "`$key`,";

                if(is_null($value)) $q2 .= "NULL,";
                else $q2 .= "'$value',";
            }

            $this->db->q("INSERT INTO `#__mec_events` (".trim($q1, ', ').") VALUES (".trim($q2, ', ').")", 'INSERT');
        }
        else
        {
            $q = "";

            foreach($event as $key=>$value)
            {
                if(is_null($value)) $q .= "`$key`=NULL,";
                else $q .= "`$key`='$value',";
            }

            $this->db->q("UPDATE `#__mec_events` SET ".trim($q, ', ')." WHERE `id`='$mec_event_id'");
        }

        // Update Schedule
        $schedule = $this->getSchedule();
        $schedule->reschedule($post_id, $schedule->get_reschedule_maximum($repeat_type));

        // Hourly Schedule
        if(!isset($this->settings['fes_section_hourly_schedule']) or (isset($this->settings['fes_section_hourly_schedule']) and $this->settings['fes_section_hourly_schedule']))
        {
            // Hourly Schedule Options
            $raw_hourly_schedules = isset($mec['hourly_schedules']) ? $mec['hourly_schedules'] : array();
            unset($raw_hourly_schedules[':d:']);

            $hourly_schedules = array();
            foreach($raw_hourly_schedules as $raw_hourly_schedule)
            {
                unset($raw_hourly_schedule['schedules'][':i:']);
                $hourly_schedules[] = $raw_hourly_schedule;
            }

            update_post_meta($post_id, 'mec_hourly_schedules', $hourly_schedules);
        }

        // Booking Options
        if(!isset($this->settings['fes_section_booking']) or (isset($this->settings['fes_section_booking']) and $this->settings['fes_section_booking']))
        {
            // Booking and Ticket Options
            $booking = isset($mec['booking']) ? $mec['booking'] : array();
            update_post_meta($post_id, 'mec_booking', $booking);

            // Tickets
            if(!isset($this->settings['fes_section_tickets']) or (isset($this->settings['fes_section_tickets']) and $this->settings['fes_section_tickets']))
            {
                $tickets = isset($mec['tickets']) ? $mec['tickets'] : array();
                unset($tickets[':i:']);

                // Unset Ticket Dats
                if(count($tickets))
                {
                    $new_tickets = array();
                    foreach($tickets as $key => $ticket)
                    {
                        unset($ticket['dates'][':j:']);

                        $ticket_start_time_ampm = ((intval($ticket['ticket_start_time_hour']) > 0 and intval($ticket['ticket_start_time_hour']) < 13) and isset($ticket['ticket_start_time_ampm'])) ? $ticket['ticket_start_time_ampm'] : '';
                        $ticket_render_start_time = date('h:ia', strtotime(sprintf('%02d', $ticket['ticket_start_time_hour']) . ':' . sprintf('%02d', $ticket['ticket_start_time_minute']) . $ticket_start_time_ampm));
                        $ticket_end_time_ampm = ((intval($ticket['ticket_end_time_hour']) > 0 and intval($ticket['ticket_end_time_hour']) < 13) and isset($ticket['ticket_end_time_ampm'])) ? $ticket['ticket_end_time_ampm'] : '';
                        $ticket_render_end_time = date('h:ia', strtotime(sprintf('%02d', $ticket['ticket_end_time_hour']) . ':' . sprintf('%02d', $ticket['ticket_end_time_minute']) . $ticket_end_time_ampm));

                        $ticket['ticket_start_time_hour'] = substr($ticket_render_start_time, 0, 2);
                        $ticket['ticket_start_time_ampm'] = strtoupper(substr($ticket_render_start_time, 5, 6));
                        $ticket['ticket_end_time_hour'] = substr($ticket_render_end_time, 0, 2);
                        $ticket['ticket_end_time_ampm'] = strtoupper(substr($ticket_render_end_time, 5, 6));
                        $ticket['price'] = trim($ticket['price']);
                        $ticket['limit'] = trim($ticket['limit']);
                        $ticket['minimum_ticket'] = trim($ticket['minimum_ticket']);
                        $ticket['stop_selling_value'] = trim($ticket['stop_selling_value']);

                        // Bellow conditional block code is used to change ticket dates format to compatible ticket past dates structure for store in db.
                        if(isset($ticket['dates']))
                        {
                            foreach($ticket['dates'] as $dates_ticket_key => $dates_ticket_values)
                            {
                                if(isset($dates_ticket_values['start']) and trim($dates_ticket_values['start']))
                                {
                                    $ticket['dates'][$dates_ticket_key]['start'] = $this->main->standardize_format($dates_ticket_values['start']);
                                }

                                if(isset($dates_ticket_values['end']) and trim($dates_ticket_values['end']))
                                {
                                    $ticket['dates'][$dates_ticket_key]['end'] = $this->main->standardize_format($dates_ticket_values['end']);
                                }
                            }
                        }

                        $new_tickets[$key] = $ticket;
                    }

                    $tickets = $new_tickets;
                }

                update_post_meta($post_id, 'mec_tickets', $tickets);
            }

            // Fees
            if(!isset($this->settings['fes_section_fees']) or (isset($this->settings['fes_section_fees']) and $this->settings['fes_section_fees']))
            {
                // Fee options
                $fees_global_inheritance = isset($mec['fees_global_inheritance']) ? sanitize_text_field($mec['fees_global_inheritance']) : 1;
                update_post_meta($post_id, 'mec_fees_global_inheritance', $fees_global_inheritance);

                $fees = isset($mec['fees']) ? $mec['fees'] : array();
                update_post_meta($post_id, 'mec_fees', $fees);
            }

            // Variation
            if(!isset($this->settings['fes_section_ticket_variations']) or (isset($this->settings['fes_section_ticket_variations']) and $this->settings['fes_section_ticket_variations']))
            {
                // Ticket Variation options
                $ticket_variations_global_inheritance = isset($mec['ticket_variations_global_inheritance']) ? sanitize_text_field($mec['ticket_variations_global_inheritance']) : 1;
                update_post_meta($post_id, 'mec_ticket_variations_global_inheritance', $ticket_variations_global_inheritance);

                $ticket_variations = isset($mec['ticket_variations']) ? $mec['ticket_variations'] : array();
                update_post_meta($post_id, 'mec_ticket_variations', $ticket_variations);
            }

            // Booking Form
            if(!isset($this->settings['fes_section_reg_form']) or (isset($this->settings['fes_section_reg_form']) and $this->settings['fes_section_reg_form']))
            {
                // Registration Fields options
                $reg_fields_global_inheritance = isset($mec['reg_fields_global_inheritance']) ? sanitize_text_field($mec['reg_fields_global_inheritance']) : 1;
                update_post_meta($post_id, 'mec_reg_fields_global_inheritance', $reg_fields_global_inheritance);

                $reg_fields = isset($mec['reg_fields']) ? $mec['reg_fields'] : array();
                if($reg_fields_global_inheritance) $reg_fields = array();

                update_post_meta($post_id, 'mec_reg_fields', $reg_fields);

                $bfixed_fields = isset($mec['bfixed_fields']) ? $mec['bfixed_fields'] : array();
                if($reg_fields_global_inheritance) $bfixed_fields = array();

                update_post_meta($post_id, 'mec_bfixed_fields', $bfixed_fields);
            }
        }

        // Organizer Payment Options
        $op = isset($mec['op']) ? $mec['op'] : array();
        update_post_meta($post_id, 'mec_op', $op);
        update_user_meta(get_post_field('post_author', $post_id), 'mec_op', $op);

        // MEC Fields
        $fields = (isset($mec['fields']) and is_array($mec['fields'])) ? $mec['fields'] : array();
        update_post_meta($post_id, 'mec_fields', $fields);

        // Save fields one by one
        foreach($fields as $field_id=>$values)
        {
            if(is_array($values))
            {
                $values = array_unique($values);
                $values = implode(',', $values);
            }

            update_post_meta($post_id, 'mec_fields_'.$field_id, sanitize_text_field($values));
        }

        // Downloadable File
        if(isset($mec['downloadable_file']))
        {
            $dl_file = isset($mec['downloadable_file']) ? sanitize_text_field($mec['downloadable_file']) : '';
            update_post_meta($post_id, 'mec_dl_file', $dl_file);
        }

        // Public Download Module File
        if(isset($mec['public_download_module_file']))
        {
            $public_dl_file = isset($mec['public_download_module_file']) ? sanitize_text_field($mec['public_download_module_file']) : '';
            update_post_meta($post_id, 'mec_public_dl_file', $public_dl_file);

            $public_dl_title = isset($mec['public_download_module_title']) ? sanitize_text_field($mec['public_download_module_title']) : '';
            update_post_meta($post_id, 'mec_public_dl_title', $public_dl_title);

            $public_dl_description = isset($mec['public_download_module_description']) ? sanitize_text_field($mec['public_download_module_description']) : '';
            update_post_meta($post_id, 'mec_public_dl_description', $public_dl_description);
        }

        // Event Gallery
        $gallery = (isset($mec['event_gallery']) and is_array($mec['event_gallery'])) ? $mec['event_gallery'] : [];
        update_post_meta($post_id, 'mec_event_gallery', $gallery);

        // Related Events
        $related_events = (isset($mec['related_events']) and is_array($mec['related_events'])) ? $mec['related_events'] : [];
        update_post_meta($post_id, 'mec_related_events', $related_events);

        // Event Banner
        $event_banner = (isset($mec['banner']) and is_array($mec['banner'])) ? $mec['banner'] : [];
        update_post_meta($post_id, 'mec_banner', $event_banner);

        // Event Dates Changed?
        if($prev_start_datetime and $prev_end_datetime and !$repeat_status and $prev_start_datetime != $start_datetime and $prev_end_datetime != $end_datetime)
        {
            $this->main->event_date_updated($post_id, $prev_start_datetime, $prev_end_datetime);
        }

        do_action('save_fes_meta_action', $post_id, $mec);

        // For Event Notification Badge.
        if(isset($_REQUEST['mec']['post_id']) and trim(sanitize_text_field($_REQUEST['mec']['post_id'])) == '-1') update_post_meta($post_id, 'mec_event_date_submit', date('YmdHis', current_time('timestamp', 0)));

        $message = '';
        if($status == 'pending') $message = esc_html__('Event submitted. It will publish as soon as possible.', 'modern-events-calendar-lite');
        elseif($status == 'publish') $message = esc_html__('The event published.', 'modern-events-calendar-lite');

        // Trigger Event
        if($method == 'updated') do_action('mec_fes_updated', $post_id , 'update');
        else do_action('mec_fes_added', $post_id , '');

        // Save Event Data
        do_action('mec_save_event_data', $post_id, $mec);

        $redirect_to = ((isset($this->settings['fes_thankyou_page']) and trim($this->settings['fes_thankyou_page'])) ? get_permalink(intval($this->settings['fes_thankyou_page'])) : '');
        if(isset($this->settings['fes_thankyou_page_url']) and trim($this->settings['fes_thankyou_page_url'])) $redirect_to = esc_url($this->settings['fes_thankyou_page_url']);

        $this->main->response(array(
            'success' => 1,
            'message' => $message,
            'data'=> array(
                'post_id' => $post_id,
                'redirect_to' => $redirect_to,
            ),
        ));
    }


    public function save_event($post_id)
    {
        // Check if our nonce is set.
        if(!isset($_POST['mec_event_nonce'])) return;

        // It's from FES
        if(isset($_POST['action']) and sanitize_text_field($_POST['action']) === 'mec_fes_form') return;

        // Verify that the nonce is valid.
        if(!wp_verify_nonce(sanitize_text_field($_POST['mec_event_nonce']), 'mec_event_data')) return;

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if(defined('DOING_AUTOSAVE') and DOING_AUTOSAVE) return;

        // Get Modern Events Calendar Data
        $_mec = isset($_POST['mec']) ? $this->main->sanitize_deep_array($_POST['mec']) : array();

        $start_date = (isset($_mec['date']['start']['date']) and trim($_mec['date']['start']['date'])) ? $this->main->standardize_format(sanitize_text_field($_mec['date']['start']['date'])) : date('Y-m-d');
        $end_date = (isset($_mec['date']['end']['date']) and trim($_mec['date']['end']['date'])) ? $this->main->standardize_format(sanitize_text_field($_mec['date']['end']['date'])) : date('Y-m-d');

        // Remove Cached Data
        wp_cache_delete($post_id, 'mec-events-data');

        $location_id = isset($_mec['location_id']) ? sanitize_text_field($_mec['location_id']) : 0;
        $dont_show_map = isset($_mec['dont_show_map']) ? sanitize_text_field($_mec['dont_show_map']) : 0;
        $organizer_id = isset($_mec['organizer_id']) ? sanitize_text_field($_mec['organizer_id']) : 0;
        $read_more = isset($_mec['read_more']) ? sanitize_url($_mec['read_more']) : '';
        $more_info = (isset($_mec['more_info']) and trim($_mec['more_info'])) ? sanitize_url($_mec['more_info']) : '';
        $more_info_title = isset($_mec['more_info_title']) ? sanitize_text_field($_mec['more_info_title']) : '';
        $more_info_target = isset($_mec['more_info_target']) ? sanitize_text_field($_mec['more_info_target']) : '';

        $cost = isset($_mec['cost']) ? sanitize_text_field($_mec['cost']) : '';
        $cost = apply_filters(
            'mec_event_cost_sanitize',
            sanitize_text_field($cost),
            $cost
        );

        $cost_auto_calculate = (isset($_mec['cost_auto_calculate']) ? sanitize_text_field($_mec['cost_auto_calculate']) : 0);
        $currency_options = ((isset($_mec['currency']) and is_array($_mec['currency'])) ? $_mec['currency'] : array());

        update_post_meta($post_id, 'mec_location_id', $location_id);
        update_post_meta($post_id, 'mec_dont_show_map', $dont_show_map);
        update_post_meta($post_id, 'mec_organizer_id', $organizer_id);
        update_post_meta($post_id, 'mec_read_more', $read_more);
        update_post_meta($post_id, 'mec_more_info', $more_info);
        update_post_meta($post_id, 'mec_more_info_title', $more_info_title);
        update_post_meta($post_id, 'mec_more_info_target', $more_info_target);
        update_post_meta($post_id, 'mec_cost', $cost);
        update_post_meta($post_id, 'mec_cost_auto_calculate', $cost_auto_calculate);
        update_post_meta($post_id, 'mec_currency', $currency_options);

        do_action('update_custom_dev_post_meta', $_mec, $post_id);

        // Additional Organizers
        $additional_organizer_ids = $_mec['additional_organizer_ids'] ?? [];

        foreach($additional_organizer_ids as $additional_organizer_id) wp_set_object_terms($post_id, (int) $additional_organizer_id, 'mec_organizer', true);
        update_post_meta($post_id, 'mec_additional_organizer_ids', $additional_organizer_ids);

        // Additional locations
        $additional_location_ids = $_mec['additional_location_ids'] ?? [];

        foreach($additional_location_ids as $additional_location_id) wp_set_object_terms($post_id, (int) $additional_location_id, 'mec_location', true);
        update_post_meta($post_id, 'mec_additional_location_ids', $additional_location_ids);

        // Date Options
        $date = isset($_mec['date']) ? $_mec['date'] : array();

        $start_date = date('Y-m-d', strtotime($start_date));

        // Set the start date
        $date['start']['date'] = $start_date;

        $start_time_hour = isset($date['start']) ? sanitize_text_field($date['start']['hour']) : '8';
        $start_time_minutes = isset($date['start']) ? sanitize_text_field($date['start']['minutes']) : '00';
        $start_time_ampm = (isset($date['start']) and isset($date['start']['ampm'])) ? sanitize_text_field($date['start']['ampm']) : 'AM';

        $end_date = date('Y-m-d', strtotime($end_date));

        // Fix end_date if it's smaller than start_date
        if(strtotime($end_date) < strtotime($start_date)) $end_date = $start_date;

        // Set the end date
        $date['end']['date'] = $end_date;

        $end_time_hour = isset($date['end']) ? sanitize_text_field($date['end']['hour']) : '6';
        $end_time_minutes = isset($date['end']) ? sanitize_text_field($date['end']['minutes']) : '00';
        $end_time_ampm = (isset($date['end']) and isset($date['end']['ampm'])) ? sanitize_text_field($date['end']['ampm']) : 'PM';

        if(isset($this->settings['time_format']) and $this->settings['time_format'] == 24)
        {
            $day_start_seconds = $this->main->time_to_seconds($this->main->to_24hours($start_time_hour, NULL, 'start'), $start_time_minutes);
            $day_end_seconds = $this->main->time_to_seconds($this->main->to_24hours($end_time_hour, NULL, 'end'), $end_time_minutes);
        }
        else
        {
            $day_start_seconds = $this->main->time_to_seconds($this->main->to_24hours($start_time_hour, $start_time_ampm, 'start'), $start_time_minutes);
            $day_end_seconds = $this->main->time_to_seconds($this->main->to_24hours($end_time_hour, $end_time_ampm, 'end'), $end_time_minutes);
        }

        if($end_date === $start_date and $day_end_seconds < $day_start_seconds)
        {
            $day_end_seconds = $day_start_seconds;

            $end_time_hour = $start_time_hour;
            $end_time_minutes = $start_time_minutes;
            $end_time_ampm = $start_time_ampm;

            $date['end']['hour'] = $start_time_hour;
            $date['end']['minutes'] = $start_time_minutes;
            $date['end']['ampm'] = $start_time_ampm;
        }

        // If 24 hours format is enabled then convert it back to 12 hours
        if(isset($this->settings['time_format']) and $this->settings['time_format'] == 24)
        {
            if($start_time_hour < 12) $start_time_ampm = 'AM';
            elseif($start_time_hour == 12) $start_time_ampm = 'PM';
            elseif($start_time_hour > 12)
            {
                $start_time_hour -= 12;
                $start_time_ampm = 'PM';
            }

            if($start_time_hour == 0)
            {
                $start_time_hour = 12;
                $start_time_ampm = 'AM';
            }

            if($end_time_hour < 12) $end_time_ampm = 'AM';
            elseif($end_time_hour == 12) $end_time_ampm = 'PM';
            elseif($end_time_hour > 12)
            {
                $end_time_hour -= 12;
                $end_time_ampm = 'PM';
            }

            if($end_time_hour == 0)
            {
                $end_time_hour = 12;
                $end_time_ampm = 'AM';
            }

            // Set converted values to date array
            $date['start']['hour'] = $start_time_hour;
            $date['start']['ampm'] = $start_time_ampm;

            $date['end']['hour'] = $end_time_hour;
            $date['end']['ampm'] = $end_time_ampm;
        }

        $allday = isset($date['allday']) ? 1 : 0;
        $one_occurrence = isset($date['one_occurrence']) ? 1 : 0;
        $hide_time = isset($date['hide_time']) ? 1 : 0;
        $hide_end_time = isset($date['hide_end_time']) ? 1 : 0;
        $comment = isset($date['comment']) ? sanitize_text_field($date['comment']) : '';
        $timezone = (isset($_mec['timezone']) and trim($_mec['timezone']) != '') ? sanitize_text_field($_mec['timezone']) : 'global';
        $countdown_method = (isset($_mec['countdown_method']) and trim($_mec['countdown_method']) != '') ? sanitize_text_field($_mec['countdown_method']) : 'global';
        $style_per_event = (isset($_mec['style_per_event']) and trim($_mec['style_per_event']) != '') ? sanitize_text_field($_mec['style_per_event']) : 'global';
        $trailer_url = (isset($_mec['trailer_url']) and trim($_mec['trailer_url']) != '') ? sanitize_url($_mec['trailer_url']) : '';
        $trailer_title = isset($_mec['trailer_title']) ? sanitize_text_field($_mec['trailer_title']) : '';
        $public = (isset($_mec['public']) and trim($_mec['public']) != '') ? sanitize_text_field($_mec['public']) : 1;

        // Set start time and end time if event is all day
        if($allday == 1)
        {
            $start_time_hour = '8';
            $start_time_minutes = '00';
            $start_time_ampm = 'AM';

            $end_time_hour = '6';
            $end_time_minutes = '00';
            $end_time_ampm = 'PM';
        }

        // Repeat Options
        $repeat = $date['repeat'] ?? array();
        $certain_weekdays = $repeat['certain_weekdays'] ?? array();

        $repeat_status = isset($repeat['status']) ? 1 : 0;
        $repeat_type = ($repeat_status and isset($repeat['type'])) ? $repeat['type'] : '';

        // Unset Repeat if no days are selected
        if($repeat_type == 'certain_weekdays' and (!is_array($certain_weekdays) or (is_array($certain_weekdays) and !count($certain_weekdays))))
        {
            $repeat_status = 0;
            $repeat['status'] = 0;
            $repeat['type'] = '';
        }

        $repeat_interval = ($repeat_status and isset($repeat['interval']) and trim($repeat['interval'])) ? $repeat['interval'] : 1;

        // Advanced Repeat
        $advanced = isset($repeat['advanced']) ? sanitize_text_field($repeat['advanced']) : '';

        if(!is_numeric($repeat_interval)) $repeat_interval = null;

        if($repeat_type == 'weekly') $interval_multiply = 7;
        else $interval_multiply = 1;

        // Reset certain weekdays if repeat type is not set to certain weekdays
        if($repeat_type != 'certain_weekdays') $certain_weekdays = array();

        if(!is_null($repeat_interval)) $repeat_interval = $repeat_interval * $interval_multiply;

        // String To Array
        if($repeat_type == 'advanced' and trim($advanced)) $advanced = explode('-', $advanced);
        else $advanced = array();

        $repeat_end = ($repeat_status and isset($repeat['end'])) ? $repeat['end'] : '';
        $repeat_end_at_occurrences = ($repeat_status && isset($repeat['end_at_occurrences']) && is_numeric($repeat['end_at_occurrences'])) ? $repeat['end_at_occurrences'] - 1 : 9;
        $repeat_end_at_date = ($repeat_status and isset($repeat['end_at_date'])) ? $this->main->standardize_format( $repeat['end_at_date'] ) : '';

        // Previous Date Times
        $prev_start_datetime = get_post_meta($post_id, 'mec_start_datetime', true);
        $prev_end_datetime = get_post_meta($post_id, 'mec_end_datetime', true);

        $start_datetime = $start_date.' '.sprintf('%02d', $start_time_hour).':'.sprintf('%02d', $start_time_minutes).' '.$start_time_ampm;
        $end_datetime = $end_date.' '.sprintf('%02d', $end_time_hour).':'.sprintf('%02d', $end_time_minutes).' '.$end_time_ampm;

        update_post_meta($post_id, 'mec_date', $date);
        update_post_meta($post_id, 'mec_repeat', $repeat);
        update_post_meta($post_id, 'mec_certain_weekdays', $certain_weekdays);
        update_post_meta($post_id, 'mec_allday', $allday);
        update_post_meta($post_id, 'one_occurrence', $one_occurrence);
        update_post_meta($post_id, 'mec_hide_time', $hide_time);
        update_post_meta($post_id, 'mec_hide_end_time', $hide_end_time);
        update_post_meta($post_id, 'mec_comment', $comment);
        update_post_meta($post_id, 'mec_timezone', $timezone);
        update_post_meta($post_id, 'mec_countdown_method', $countdown_method);
        update_post_meta($post_id, 'mec_style_per_event', $style_per_event);
        update_post_meta($post_id, 'mec_trailer_url', $trailer_url);
        update_post_meta($post_id, 'mec_trailer_title', $trailer_title);
        update_post_meta($post_id, 'mec_public', $public);

        do_action('update_custom_post_meta', $date, $post_id);

        update_post_meta($post_id, 'mec_start_date', $start_date);
        update_post_meta($post_id, 'mec_start_time_hour', $start_time_hour);
        update_post_meta($post_id, 'mec_start_time_minutes', $start_time_minutes);
        update_post_meta($post_id, 'mec_start_time_ampm', $start_time_ampm);
        update_post_meta($post_id, 'mec_start_day_seconds', $day_start_seconds);
        update_post_meta($post_id, 'mec_start_datetime', $start_datetime);

        update_post_meta($post_id, 'mec_end_date', $end_date);
        update_post_meta($post_id, 'mec_end_time_hour', $end_time_hour);
        update_post_meta($post_id, 'mec_end_time_minutes', $end_time_minutes);
        update_post_meta($post_id, 'mec_end_time_ampm', $end_time_ampm);
        update_post_meta($post_id, 'mec_end_day_seconds', $day_end_seconds);
        update_post_meta($post_id, 'mec_end_datetime', $end_datetime);

        update_post_meta($post_id, 'mec_repeat_status', $repeat_status);
        update_post_meta($post_id, 'mec_repeat_type', $repeat_type);
        update_post_meta($post_id, 'mec_repeat_interval', $repeat_interval);
        update_post_meta($post_id, 'mec_repeat_end', $repeat_end);
        update_post_meta($post_id, 'mec_repeat_end_at_occurrences', $repeat_end_at_occurrences);
        update_post_meta($post_id, 'mec_repeat_end_at_date', $repeat_end_at_date);
        update_post_meta($post_id, 'mec_advanced_days', $advanced);

        // Event Sequence (Used in iCal feed)
        $sequence = (int) get_post_meta($post_id, 'mec_sequence', true);
        update_post_meta($post_id, 'mec_sequence', ($sequence + 1));

        // For Event Notification Badge.
        if(!current_user_can('administrator')) update_post_meta($post_id, 'mec_event_date_submit', date('YmdHis', current_time('timestamp', 0)));

        // Creating $event array for inserting in mec_events table
        $event = array(
            'post_id' => $post_id,
            'start' => $start_date,
            'repeat' => $repeat_status,
            'rinterval' => (!in_array($repeat_type, array('daily', 'weekly', 'monthly')) ? null : $repeat_interval),
            'time_start' => $day_start_seconds,
            'time_end' => $day_end_seconds,
        );

        $year = null;
        $month = null;
        $day = null;
        $week = null;
        $weekday = null;
        $weekdays = null;

        // MEC weekdays
        $mec_weekdays = $this->main->get_weekdays();

        // MEC weekends
        $mec_weekends = $this->main->get_weekends();

        $plus_date = '';
        if($repeat_type == 'daily')
        {
            $plus_date = '+' . $repeat_end_at_occurrences * $repeat_interval . ' Days';
        }
        elseif($repeat_type == 'weekly')
        {
            $plus_date = '+' . $repeat_end_at_occurrences * ($repeat_interval) . ' Days';
        }
        elseif($repeat_type == 'weekday')
        {
            $repeat_interval = 1;
            $plus_date = '+' . $repeat_end_at_occurrences * $repeat_interval . ' Weekdays';

            $weekdays = ',' . implode(',', $mec_weekdays) . ',';
        }
        elseif($repeat_type == 'weekend')
        {
            $repeat_interval = 1;
            $plus_date = '+' . round($repeat_end_at_occurrences / 2) * ($repeat_interval * 7) . ' Days';

            $weekdays = ',' . implode(',', $mec_weekends) . ',';
        }
        elseif($repeat_type == 'certain_weekdays')
        {
            $repeat_interval = 1;
            $plus_date = '+' . ceil(($repeat_end_at_occurrences * $repeat_interval) * (7 / count($certain_weekdays))) . ' days';

            $weekdays = ',' . implode(',', $certain_weekdays) . ',';
        }
        elseif($repeat_type == 'monthly')
        {
            $plus_date = '+' . $repeat_end_at_occurrences * $repeat_interval . ' Months';

            $year = '*';
            $month = '*';

            $s = $start_date;
            $e = $end_date;

            $_days = array();
            while(strtotime($s) <= strtotime($e))
            {
                $_days[] = date('d', strtotime($s));
                $s = date('Y-m-d', strtotime('+1 Day', strtotime($s)));
            }

            $day = ',' . implode(',', array_unique($_days)) . ',';

            $week = '*';
            $weekday = '*';
        }
        elseif($repeat_type == 'yearly')
        {
            $plus_date = '+' . $repeat_end_at_occurrences * $repeat_interval . ' Years';

            $year = '*';

            $s = $start_date;
            $e = $end_date;

            $_months = array();
            $_days = array();
            while(strtotime($s) <= strtotime($e))
            {
                $_months[] = date('m', strtotime($s));
                $_days[] = date('d', strtotime($s));

                $s = date('Y-m-d', strtotime('+1 Day', strtotime($s)));
            }

            $_months = array_unique($_months);

            $month = ',' . implode(',', array($_months[0])) . ',';
            $day = ',' . implode(',', array_unique($_days)) . ',';

            $week = '*';
            $weekday = '*';
        }
        elseif($repeat_type == "advanced")
        {
            // Render class object
            $this->render = $this->getRender();

            // Get finish date
            $event_info = array('start' => $date['start'], 'end' => $date['end']);
            $dates = $this->render->generate_advanced_days($advanced, $event_info, $repeat_end_at_occurrences, $start_date, 'events');

            $period_date = $this->main->date_diff($start_date, end($dates)['end']['date']);
            $plus_date = '+' . $period_date->days . ' Days';
        }

        $in_days_arr = (isset($_mec['in_days']) and is_array($_mec['in_days']) and count($_mec['in_days'])) ? array_unique($_mec['in_days']) : array();
        $not_in_days_arr = (isset($_mec['not_in_days']) and is_array($_mec['not_in_days']) and count($_mec['not_in_days'])) ? array_unique($_mec['not_in_days']) : array();

        $in_days = '';
        if(count($in_days_arr))
        {
            if(isset($in_days_arr[':i:'])) unset($in_days_arr[':i:']);

            $in_days_arr = array_map(function($value)
            {
                $ex = explode(':', $value);

                $in_days_times = '';
                if(isset($ex[2]) and isset($ex[3]))
                {
                    $in_days_start_time = $ex[2];
                    $in_days_end_time = $ex[3];

                    // If 24 hours format is enabled then convert it back to 12 hours
                    if(isset($this->settings['time_format']) and $this->settings['time_format'] == 24)
                    {
                        $ex_start_time = explode('-', $in_days_start_time);
                        $ex_end_time = explode('-', $in_days_end_time);

                        $in_days_start_hour = $ex_start_time[0];
                        $in_days_start_minutes = $ex_start_time[1];
                        $in_days_start_ampm = $ex_start_time[2];

                        $in_days_end_hour = $ex_end_time[0];
                        $in_days_end_minutes = $ex_end_time[1];
                        $in_days_end_ampm = $ex_end_time[2];

                        if(trim($in_days_start_ampm) == '')
                        {
                            if($in_days_start_hour < 12) $in_days_start_ampm = 'AM';
                            elseif($in_days_start_hour == 12) $in_days_start_ampm = 'PM';
                            elseif($in_days_start_hour > 12)
                            {
                                $in_days_start_hour -= 12;
                                $in_days_start_ampm = 'PM';
                            }
                            elseif($in_days_start_hour == 0)
                            {
                                $in_days_start_hour = 12;
                                $in_days_start_ampm = 'AM';
                            }
                        }

                        if(trim($in_days_end_ampm) == '')
                        {
                            if($in_days_end_hour < 12) $in_days_end_ampm = 'AM';
                            elseif($in_days_end_hour == 12) $in_days_end_ampm = 'PM';
                            elseif($in_days_end_hour > 12)
                            {
                                $in_days_end_hour -= 12;
                                $in_days_end_ampm = 'PM';
                            }
                            elseif($in_days_end_hour == 0)
                            {
                                $in_days_end_hour = 12;
                                $in_days_end_ampm = 'AM';
                            }
                        }

                        if(strlen($in_days_start_hour) == 1) $in_days_start_hour = '0'.$in_days_start_hour;
                        if(strlen($in_days_start_minutes) == 1) $in_days_start_minutes = '0'.$in_days_start_minutes;

                        if(strlen($in_days_end_hour) == 1) $in_days_end_hour = '0'.$in_days_end_hour;
                        if(strlen($in_days_end_minutes) == 1) $in_days_end_minutes = '0'.$in_days_end_minutes;

                        $in_days_start_time = $in_days_start_hour.'-'.$in_days_start_minutes.'-'.$in_days_start_ampm;
                        $in_days_end_time = $in_days_end_hour.'-'.$in_days_end_minutes.'-'.$in_days_end_ampm;
                    }

                    $in_days_times = ':'.$in_days_start_time.':'.$in_days_end_time;
                }

                return $this->main->standardize_format($ex[0]) . ':' . $this->main->standardize_format($ex[1]).$in_days_times;
            }, $in_days_arr);

            usort($in_days_arr, function($a, $b)
            {
                $ex_a = explode(':', $a);
                $ex_b = explode(':', $b);

                $date_a = $ex_a[0];
                $date_b = $ex_b[0];

                $in_day_a_time_label = '';
                if(isset($ex_a[2]))
                {
                    $in_day_a_time = $ex_a[2];
                    $pos = strpos($in_day_a_time, '-');
                    if($pos !== false) $in_day_a_time_label = substr_replace($in_day_a_time, ':', $pos, 1);

                    $in_day_a_time_label = str_replace('-', ' ', $in_day_a_time_label);
                }

                $in_day_b_time_label = '';
                if(isset($ex_b[2]))
                {
                    $in_day_b_time = $ex_b[2];
                    $pos = strpos($in_day_b_time, '-');
                    if($pos !== false) $in_day_b_time_label = substr_replace($in_day_b_time, ':', $pos, 1);

                    $in_day_b_time_label = str_replace('-', ' ', $in_day_b_time_label);
                }

                return strtotime(trim($date_a.' '.$in_day_a_time_label)) - strtotime(trim($date_b.' '.$in_day_b_time_label));
            });

            if(!isset($in_days_arr[':i:'])) $in_days_arr[':i:'] = ':val:';
            foreach($in_days_arr as $key => $in_day_arr)
            {
                if(is_numeric($key)) $in_days .= $in_day_arr . ',';
            }
        }

        $not_in_days = '';
        if(count($not_in_days_arr))
        {
            foreach($not_in_days_arr as $key => $not_in_day_arr)
            {
                if(is_numeric($key)) $not_in_days .= $this->main->standardize_format( $not_in_day_arr ) . ',';
            }
        }

        $in_days = trim($in_days, ', ');
        $not_in_days = trim($not_in_days, ', ');

        update_post_meta($post_id, 'mec_in_days', $in_days);
        update_post_meta($post_id, 'mec_not_in_days', $not_in_days);

        // Repeat End Date
        if($repeat_end == 'date') $repeat_end_date = $repeat_end_at_date;
        elseif($repeat_end == 'occurrences')
        {
            if($plus_date) $repeat_end_date = date('Y-m-d', strtotime($plus_date, strtotime($end_date)));
            else $repeat_end_date = '0000-00-00';
        }
        else $repeat_end_date = '0000-00-00';

        // If event is not repeating then set the end date of event correctly
        if(!$repeat_status or $repeat_type == 'custom_days') $repeat_end_date = $end_date;

        // Add parameters to the $event
        $event['end'] = $repeat_end_date;
        $event['year'] = $year;
        $event['month'] = $month;
        $event['day'] = $day;
        $event['week'] = $week;
        $event['weekday'] = $weekday;
        $event['weekdays'] = $weekdays;
        $event['days'] = $in_days;
        $event['not_in_days'] = $not_in_days;

        // Update MEC Events Table
        $mec_event_id = $this->db->select("SELECT `id` FROM `#__mec_events` WHERE `post_id`='$post_id'", 'loadResult');

        if(!$mec_event_id)
        {
            $q1 = '';
            $q2 = '';

            foreach($event as $key => $value)
            {
                $q1 .= "`$key`,";

                if(is_null($value)) $q2 .= 'NULL,';
                else $q2 .= "'$value',";
            }

            $this->db->q('INSERT INTO `#__mec_events` (' . trim($q1, ', ') . ') VALUES (' . trim($q2, ', ') . ')', 'INSERT');
        }
        else
        {
            $q = '';

            foreach($event as $key => $value)
            {
                if(is_null($value)) $q .= "`$key`=NULL,";
                else $q .= "`$key`='$value',";
            }

            $this->db->q('UPDATE `#__mec_events` SET ' . trim($q, ', ') . " WHERE `id`='$mec_event_id'");
        }

        // Update Schedule
        $schedule = $this->getSchedule();
        $schedule->reschedule($post_id, $schedule->get_reschedule_maximum($repeat_type));

        // Hourly Schedule Options
        $raw_hourly_schedules = isset($_mec['hourly_schedules']) ? $_mec['hourly_schedules'] : array();
        unset($raw_hourly_schedules[':d:']);

        $hourly_schedules = array();
        foreach($raw_hourly_schedules as $raw_hourly_schedule)
        {
            if(isset($raw_hourly_schedule['schedules'][':i:'])) unset($raw_hourly_schedule['schedules'][':i:']);
            $hourly_schedules[] = $raw_hourly_schedule;
        }

        update_post_meta($post_id, 'mec_hourly_schedules', $hourly_schedules);

        // Booking and Ticket Options
        $booking = $_mec['booking'] ?? array();
        update_post_meta($post_id, 'mec_booking', $booking);

        $tickets = $_mec['tickets'] ?? array();
        if(isset($tickets[':i:'])) unset($tickets[':i:']);

        // Unset Ticket Dats
        if(count($tickets))
        {
            $new_tickets = array();
            foreach($tickets as $key => $ticket)
            {
                unset($ticket['dates'][':j:']);
                $ticket_start_time_ampm = ((isset($ticket['ticket_start_time_hour']) and (intval($ticket['ticket_start_time_hour']) > 0 and intval($ticket['ticket_start_time_hour']) < 13) and isset($ticket['ticket_start_time_ampm'])) ? $ticket['ticket_start_time_ampm'] : '');
                $ticket_render_start_time = ((isset($ticket['ticket_start_time_hour']) and $ticket['ticket_start_time_hour']) ? date('h:ia', strtotime(sprintf('%02d', $ticket['ticket_start_time_hour']) . ':' . sprintf('%02d', $ticket['ticket_start_time_minute']) . $ticket_start_time_ampm)) : '');
                $ticket_end_time_ampm = ((isset($ticket['ticket_end_time_hour']) and (intval($ticket['ticket_end_time_hour']) > 0 and intval($ticket['ticket_end_time_hour']) < 13) and isset($ticket['ticket_end_time_ampm'])) ? $ticket['ticket_end_time_ampm'] : '');
                $ticket_render_end_time = ((isset($ticket['ticket_end_time_hour']) and $ticket['ticket_end_time_hour']) ? date('h:ia', strtotime(sprintf('%02d', $ticket['ticket_end_time_hour']) . ':' . sprintf('%02d', $ticket['ticket_end_time_minute']) . $ticket_end_time_ampm)) : '');

                $ticket['ticket_start_time_hour'] = substr($ticket_render_start_time, 0, 2);
                $ticket['ticket_start_time_ampm'] = strtoupper(substr($ticket_render_start_time, 5, 6));
                $ticket['ticket_end_time_hour'] = substr($ticket_render_end_time, 0, 2);
                $ticket['ticket_end_time_ampm'] = strtoupper(substr($ticket_render_end_time, 5, 6));
                $ticket['price'] = trim($ticket['price']);
                $ticket['limit'] = trim($ticket['limit']);
                $ticket['minimum_ticket'] = trim($ticket['minimum_ticket']);
                $ticket['stop_selling_value'] = trim($ticket['stop_selling_value']);
                $ticket['category_ids'] = isset( $ticket['category_ids'] ) && !empty( $ticket['category_ids'] ) ? (array)$ticket['category_ids'] : [];

                // Bellow conditional block code is used to change ticket dates format to compatible ticket past dates structure for store in db.
                if(isset($ticket['dates']))
                {
                    foreach($ticket['dates'] as $dates_ticket_key => $dates_ticket_values)
                    {
                        if(isset($dates_ticket_values['start']) and trim($dates_ticket_values['start']))
                        {
                            $ticket['dates'][$dates_ticket_key]['start'] = $this->main->standardize_format($dates_ticket_values['start']);
                        }

                        if(isset($dates_ticket_values['end']) and trim($dates_ticket_values['end']))
                        {
                            $ticket['dates'][$dates_ticket_key]['end'] = $this->main->standardize_format($dates_ticket_values['end']);
                        }
                    }
                }

                $ticket['id'] = $key;
                $new_tickets[$key] = $ticket;
            }

            $tickets = $new_tickets;
        }

        update_post_meta($post_id, 'mec_tickets', $tickets);

        // Fee options
        $fees_global_inheritance = isset($_mec['fees_global_inheritance']) ? sanitize_text_field($_mec['fees_global_inheritance']) : 1;
        update_post_meta($post_id, 'mec_fees_global_inheritance', $fees_global_inheritance);

        $fees = $_mec['fees'] ?? array();
        if(isset($fees[':i:'])) unset($fees[':i:']);

        update_post_meta($post_id, 'mec_fees', $fees);

        // Ticket Variations options
        $ticket_variations_global_inheritance = isset($_mec['ticket_variations_global_inheritance']) ? sanitize_text_field($_mec['ticket_variations_global_inheritance']) : 1;
        update_post_meta($post_id, 'mec_ticket_variations_global_inheritance', $ticket_variations_global_inheritance);

        $ticket_variations = $_mec['ticket_variations'] ?? array();
        if(isset($ticket_variations[':i:'])) unset($ticket_variations[':i:']);

        update_post_meta($post_id, 'mec_ticket_variations', $ticket_variations);

        // Registration Fields options
        $reg_fields_global_inheritance = isset($_mec['reg_fields_global_inheritance']) ? sanitize_text_field($_mec['reg_fields_global_inheritance']) : 1;
        update_post_meta($post_id, 'mec_reg_fields_global_inheritance', $reg_fields_global_inheritance);

        $reg_fields = $_mec['reg_fields'] ?? array();
        if($reg_fields_global_inheritance) $reg_fields = array();

        do_action('mec_save_reg_fields', $post_id, $reg_fields);
        update_post_meta($post_id, 'mec_reg_fields', $reg_fields);

        $bfixed_fields = $_mec['bfixed_fields'] ?? array();
        if($reg_fields_global_inheritance) $bfixed_fields = array();

        do_action('mec_save_bfixed_fields', $post_id, $bfixed_fields);
        update_post_meta($post_id, 'mec_bfixed_fields', $bfixed_fields);

        // Organizer Payment Options
        $op = $_mec['op'] ?? array();
        update_post_meta($post_id, 'mec_op', $op);
        update_user_meta(get_post_field('post_author', $post_id), 'mec_op', $op);

        // MEC Fields
        $fields = (isset($_mec['fields']) and is_array($_mec['fields'])) ? $_mec['fields'] : array();
        update_post_meta($post_id, 'mec_fields', $fields);

        // Save fields one by one
        foreach($fields as $field_id=>$values)
        {
            if(is_array($values))
            {
                $values = array_unique($values);
                $values = implode(',', $values);
            }

            update_post_meta($post_id, 'mec_fields_'.$field_id, sanitize_text_field($values));
        }

        // Downloadable File
        if(isset($_mec['downloadable_file']))
        {
            $dl_file = sanitize_text_field($_mec['downloadable_file']);
            update_post_meta($post_id, 'mec_dl_file', $dl_file);
        }

        // Public Download Module File
        if(isset($_mec['public_download_module_file']))
        {
            $public_dl_file = isset($_mec['public_download_module_file']) ? sanitize_text_field($_mec['public_download_module_file']) : '';
            update_post_meta($post_id, 'mec_public_dl_file', $public_dl_file);

            $public_dl_title = isset($_mec['public_download_module_title']) ? sanitize_text_field($_mec['public_download_module_title']) : '';
            update_post_meta($post_id, 'mec_public_dl_title', $public_dl_title);

            $public_dl_description = isset($_mec['public_download_module_description']) ? sanitize_text_field($_mec['public_download_module_description']) : '';
            update_post_meta($post_id, 'mec_public_dl_description', $public_dl_description);
        }

        // Event Gallery
        $gallery = (isset($_mec['event_gallery']) and is_array($_mec['event_gallery'])) ? $_mec['event_gallery'] : [];
        update_post_meta($post_id, 'mec_event_gallery', $gallery);

        // Related Events
        $related_events = (isset($_mec['related_events']) and is_array($_mec['related_events'])) ? $_mec['related_events'] : [];
        update_post_meta($post_id, 'mec_related_events', $related_events);

        // Event Banner
        $event_banner = (isset($_mec['banner']) and is_array($_mec['banner'])) ? $_mec['banner'] : [];
        update_post_meta($post_id, 'mec_banner', $event_banner);

        // Notifications
        if(isset($_mec['notifications']))
        {
            $notifications = (isset($_mec['notifications']) and is_array($_mec['notifications'])) ? $_mec['notifications'] : array();
            update_post_meta($post_id, 'mec_notifications', $notifications);
        }

        // Event Dates Changed?
        if($prev_start_datetime and $prev_end_datetime and !$repeat_status and $prev_start_datetime != $start_datetime and $prev_end_datetime != $end_datetime)
        {
            $this->main->event_date_updated($post_id, $prev_start_datetime, $prev_end_datetime);
        }

        $mec_update = (isset($_REQUEST['original_publish']) and strtolower(trim(sanitize_text_field($_REQUEST['original_publish']))) == 'publish') ? false : true;
        do_action('mec_after_publish_admin_event', $post_id, $mec_update);

        // Save Event Data
        do_action('mec_save_event_data', $post_id, $_mec);
    }

?>
, north.nancy.ann@gmail.com, events@rootrivercurrent.org, torgrimson.pat@gmail.com 