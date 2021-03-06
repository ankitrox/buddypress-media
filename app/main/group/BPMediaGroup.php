<?php

/**
 * Description of BPMediaGroup
 *
 * @author faishal
 */
class BPMediaGroup {

    function __construct($initFlag = true) {
        if ($initFlag) {
            if (class_exists('BPMediaGroupsExtension')) :
                bp_register_group_extension('BPMediaGroupsExtension');
                new BPMediaGroupImage ();
                new BPMediaGroupAlbum();
                new BPMediaGroupMusic();
                new BPMediaGroupVideo();
            endif;
            add_action('bp_actions', array($this, 'custom_nav'), 999);
            add_filter('bp_media_multipart_params_filter', array($this, 'multipart_params_handler'));
        }
    }

    /**
     * Handles the custom navigation structure of the BuddyPress Group Extension Media
     *
     * @uses global $bp
     *
     * @since BuddyPress Media 2.3
     */
    function custom_nav() {
        global $bp;
        $current_group = isset($bp->groups->current_group->slug) ? $bp->groups->current_group->slug : null;
        if (!$current_group)
            return;
        if (!(isset($bp->bp_options_nav[$current_group]) && is_array($bp->bp_options_nav[$current_group])))
            return;

        /** This line might break a thing or two in custom themes and widgets */
        remove_filter('bp_activity_get_user_join_filter', 'activity_query_filter', 10);

        foreach ($bp->bp_options_nav[$current_group] as $key => $nav_item) {
            switch ($nav_item['slug']) {
                case BP_MEDIA_IMAGES_SLUG:
                case BP_MEDIA_VIDEOS_SLUG:
                case BP_MEDIA_AUDIO_SLUG:
                case BP_MEDIA_ALBUMS_SLUG:
                    unset($bp->bp_options_nav[$current_group][$key]);
            }
            switch ($bp->current_action) {
                case BP_MEDIA_IMAGES_SLUG:
                case BP_MEDIA_VIDEOS_SLUG:
                case BP_MEDIA_AUDIO_SLUG:
                case BP_MEDIA_ALBUMS_SLUG:
                    $count = count($bp->action_variables);
                    for ($i = $count; $i > 0; $i--) {
                        $bp->action_variables[$i] = $bp->action_variables[$i - 1];
                    }
                    $bp->action_variables[0] = $bp->current_action;
                    $bp->current_action = BP_MEDIA_SLUG;
            }
        }
    }

    /**
     * Adds the current group id as parameter for plupload
     *
     * @param Array $multipart_params Array of Multipart Parameters to be passed on to plupload script
     *
     * @since BuddyPress Media 2.3
     */
    function multipart_params_handler($multipart_params) {
        if (is_array($multipart_params)) {
            global $bp;
            if (isset($bp->current_action) && $bp->current_action == BP_MEDIA_SLUG
                    && isset($bp->action_variables) && empty($bp->action_variables)
                    && isset($bp->current_component) && $bp->current_component == 'groups'
                    && isset($bp->groups->current_group->id)) {
                $multipart_params['bp_media_group_id'] = $bp->groups->current_group->id;
            }
        }
        return $multipart_params;
    }

    /**
     * Displays the navigation available to the group media tab for the
     * logged in user.
     *
     * @uses $bp Global Variable set by BuddyPress
     *
     * @since BuddyPress Media 2.3
     */
    static function navigation_menu() {
        global $bp;
        if (!isset($bp->current_action) || $bp->current_action != BP_MEDIA_SLUG)
            return false;
        $current_tab = BPMediaGroup::can_upload() ? BP_MEDIA_UPLOAD_SLUG : BP_MEDIA_IMAGES_SLUG;
        if (isset($bp->action_variables[0])) {
            $current_tab = $bp->action_variables[0];
        }

        if (BPMediaGroup::can_upload()) {
            $bp_media_nav[BP_MEDIA_UPLOAD_SLUG] = array(
                'url' => trailingslashit(bp_get_group_permalink($bp->groups->current_group)) . BP_MEDIA_SLUG,
                'label' => BP_MEDIA_UPLOAD_LABEL,
            );
        } else {
            $bp_media_nav = array();
        }

        foreach (array('IMAGES', 'VIDEOS', 'AUDIO', 'ALBUMS') as $type) {
            $bp_media_nav[constant('BP_MEDIA_' . $type . '_SLUG')] = array(
                'url' => trailingslashit(bp_get_group_permalink($bp->groups->current_group)) . constant('BP_MEDIA_' . $type . '_SLUG'),
                'label' => constant('BP_MEDIA_' . $type . '_LABEL'),
            );
        }

        /** This variable will be used to display the tabs in group component */
        $bp_media_group_tabs = apply_filters('bp_media_group_tabs', $bp_media_nav, $current_tab);
        ?>
        <div class="item-list-tabs no-ajax bp-media-group-navigation" id="subnav">
            <ul>
                <?php
                foreach ($bp_media_group_tabs as $tab_slug => $tab_info) {
                    echo '<li id="' . $tab_slug . '-group-li" ' . ($current_tab == $tab_slug ? 'class="current selected"' : '') . '><a id="' . $tab_slug . '" href="' . $tab_info['url'] . '" title="' . __($tab_info['label'], BP_MEDIA_TXT_DOMAIN) . '">' . __($tab_info['label'], BP_MEDIA_TXT_DOMAIN) . '</a></li>';
                }
                ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Checks whether the current logged in user has the ability to upload on
     * the given group or not
     *
     * @since BuddyPress Media 2.3
     */
    static function can_upload() {
        /** @todo Implementation Pending */
        global $bp;
        if (isset($bp->loggedin_user->id) && is_numeric($bp->loggedin_user->id)) {
            return groups_is_user_member($bp->loggedin_user->id, bp_get_current_group_id());
        } else {
            return false;
        }

        return true;
    }

    /**
     * Adds the Media Settings menu for groups in the admin bar
     *
     * @uses global $bp,$wp_admin_bar
     *
     * @since BuddyPress Media 2.3
     */
    function admin_bar() {
        global $wp_admin_bar, $bp;
        $wp_admin_bar->add_menu(array(
            'parent' => $bp->group_admin_menu_id,
            'id' => 'bp-media-group',
            'title' => __('Media Settings', BP_MEDIA_TXT_DOMAIN),
            'href' => bp_get_groups_action_link('admin/media')
        ));
    }

    //add_action('admin_bar_menu','admin_bar',99);
    /* This will need some handling for checking if its a single group page or not, also whether the person can
     * edit media settings or not
     */

    /**
     * Checks whether a user can create an album in the given group or not
     *
     * @param string $group_id The group id to check against
     * @param string $user_id The user to be checked for permission
     *
     * @return boolean True if the user can create an album in the group, false if not
     */
    static function user_can_create_album($group_id, $user_id = 0) {
        if ($user_id == 0)
            $user_id = get_current_user_id();
        $current_level = groups_get_groupmeta($group_id, 'bp_media_group_control_level');
        switch ($current_level) {
            case 'all':
                return groups_is_user_member($user_id, $group_id) || groups_is_user_mod($user_id, $group_id) || groups_is_user_admin($user_id, $group_id);
                break;
            case 'moderators':
                return groups_is_user_mod($user_id, $group_id) || groups_is_user_admin($user_id, $group_id);
                break;
            case 'admin':
                return groups_is_user_admin($user_id, $group_id);
                break;
            default :
                return groups_is_user_admin($user_id, $group_id);
        }
        return false;
    }

    static function bp_media_display_error($errorMessage) {
        ?>
        <div id="message" class="error">
            <p>
        <?php _e($errorMessage, BP_MEDIA_TXT_DOMAIN); ?>
            </p>
        </div>
        <?php
    }

}
