<?php

/*-----------------------------------------------------------------------------------*/
/* Adjust image from instagram on post save
/*-----------------------------------------------------------------------------------*/
function catch_new_instagram_post() {
    
    global $post;
    $post_id = $post->ID;

	// If this is just a revision, don't do anything
	if ( wp_is_post_revision( $post_id ) ){
		return;
	}

    if(has_tag('Instagram',$post_id)){ //adjust to match feed

        // unhook actions so they doesn't loop infinitely
        remove_action('the_post', 'catch_new_instagram_post' );
        remove_action('save_post', 'catch_new_instagram_post');
        remove_action('draft_to_publish', 'catch_new_instagram_post');
        remove_action('new_to_publish', 'catch_new_instagram_post');
        remove_action('pending_to_publish', 'catch_new_instagram_post');
        remove_action('future_to_publish', 'catch_new_instagram_post');
             
        //basic post info
        $thisPost = get_post($post_id);
        $existingContent = $thisPost->post_content;
        $existingTitle = $thisPost->post_title;
    
        $searchImage = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $existingContent, $matches);
        
        if($searchImage && !empty($searchImage)){
            $firstImg = $matches[1][0];

            //clean the image source from ?cache.... coming from instagram. this would prevent the image from being imported to media and used as featured image
            $cleanImgSrc = strtok($firstImg,'?');
            
            //update content
            $newContent = preg_replace("/<img[^>]+\>/i",'',$existingContent,1);
             
            //if image does not exist on server
            $currentTime = date(Y.'/'.m);
            $upload_dir = wp_upload_dir();
            $uploadpath = $upload_dir['basedir'].'/'.$currentTime.'/';
            $uploadurl = $upload_dir['baseurl'].'/'.$currentTime.'/'; 
             
            //get basename
            $imageBaseName = basename($cleanImgSrc);
             
            //get image size (to check whether it exists). must be full url
            $sourceImgSize = getimagesize($cleanImgSrc);
            $destinationImgSize = getimagesize($uploadpath . $imageBaseName);
            
            //if image sizes (image exists or does not)
                 if(!$destinationImgSize && $sourceImgSize && !empty($searchImage)){
                    
                    //put file in correct uploads dir if it's not there yet
                    file_put_contents($uploadpath . $imageBaseName,file_get_contents($cleanImgSrc));
                    chmod($uploadpath . $imageBaseName, 0775);
                    
                    //add image to media library and create meta info
                    $filetype = wp_check_filetype(basename($uploadpath . $imageBaseName), null );
                    $attachment = array(
                        'guid'           => $uploadpath . $imageBaseName, 
                        'post_mime_type' => $filetype['type'],
                        'post_title'     => preg_replace( '/\.[^.]+$/', '', $imageBaseName),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                        'post_author' => 2 //an author
                    );
                    $attach_id = wp_insert_attachment( $attachment, $uploadpath . $imageBaseName, $post_id);
					
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
					
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $uploadpath . $imageBaseName);
                    wp_update_attachment_metadata( $attach_id,  $attach_data );

                    //if the current post does not have a featured image, attach the new image as featured
                    if (!has_post_thumbnail($post_id)) {
                        set_post_thumbnail($post_id, $attach_id);
                    }
                 }
                
            //update post with new info
             $newPostInfo = array(
             //'ID'           => $post_id,
             'post_title'   => $existingTitle,
             'post_content' => $newContent,
             'post_author' => 2 //make sure the author is the one we want...same as author specified for media
             );
             wp_update_post($newPostInfo);
        
        }
        
        //reinitialize actions
        add_action('the_post', 'catch_new_instagram_post' );
        add_action('save_post', 'catch_new_instagram_post');
        add_action('draft_to_publish', 'catch_new_instagram_post');
        add_action('new_to_publish', 'catch_new_instagram_post');
        add_action('pending_to_publish', 'catch_new_instagram_post');
        add_action('future_to_publish', 'catch_new_instagram_post');
    }
    
    return;
}
add_action('the_post', 'catch_new_instagram_post' );
add_action('save_post', 'catch_new_instagram_post');
add_action('draft_to_publish', 'catch_new_instagram_post');
add_action('new_to_publish', 'catch_new_instagram_post');
add_action('pending_to_publish', 'catch_new_instagram_post');
add_action('future_to_publish', 'catch_new_instagram_post');
