<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 *
 * This file is a part of Codendi.
 *
 * Codendi is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Codendi is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Codendi. If not, see <http://www.gnu.org/licenses/>.
 */



class Tracker_NotificationsManager {

    protected $tracker;

    public function __construct($tracker) {
        $this->tracker = $tracker;
    }

    public function process(TrackerManager $tracker_manager, Codendi_Request $request, $current_user) {
        if ($request->exist('stop_notification')) {
            if ($this->tracker->stop_notification != $request->get('stop_notification')) {
                $this->tracker->stop_notification = $request->get('stop_notification') ? 1 : 0;
                $dao                              = new TrackerDao();
                if ($dao->save($this->tracker)) {
                    $GLOBALS['Response']->addFeedback('info', $GLOBALS['Language']->getText('plugin_tracker_admin_notification', 'successfully_updated'));
                }
            }
        }

        if ($global_notification_data = $request->get('global_notification')) {
            if (!empty($global_notification_data)) {
                $this->processGlobalNotificationDataForUpdate($global_notification_data);
            }
        }

        $this->createNewGlobalNotification($request);
        $this->deleteGlobalNotification($request);

        $this->displayAdminNotifications($tracker_manager, $request, $current_user);
        $reminderRenderer = new Tracker_DateReminderRenderer($this->tracker);

        if ($this->tracker->userIsAdmin($current_user)) {
            $reminderRenderer->displayDateReminders($request);
        }

        $reminderRenderer->displayFooter($tracker_manager);
    }

    private function createNewGlobalNotification(Codendi_Request $request)
    {
        if ($request->exist('new_global_notification')) {
            $global_notification_data = $request->get('new_global_notification');

            if ($global_notification_data['addresses'] !== '') {
                $this->addGlobalNotification(
                    $global_notification_data['addresses'],
                    $global_notification_data['all_updates'],
                    $global_notification_data['check_permissions']
                );
            }
        }
    }

    private function deleteGlobalNotification(Codendi_Request $request)
    {
        if ($request->exist('remove_global')) {
            foreach ($request->get('remove_global') as $notification_id => $value) {
                $this->removeGlobalNotification($notification_id);
            }
        }
    }

    protected function displayAdminNotifications(TrackerManager $tracker_manager, $request, $current_user) {
        $this->tracker->displayAdminItemHeader($tracker_manager, 'editnotifications');
        echo '<fieldset><form action="'.TRACKER_BASE_URL.'/?tracker='. (int)$this->tracker->id .'&amp;func=admin-notifications" method="POST">';

        $this->displayAdminNotifications_Toggle();
        $this->displayAdminNotifications_Global($request);

        echo'
        <input class="btn btn-primary" type="submit" name="submit" value="'.$GLOBALS['Language']->getText('plugin_tracker_include_artifact','submit').'"/>
        </FORM></fieldset>';
    }

    protected function displayAdminNotifications_Toggle() {
        if ($this->tracker->userIsAdmin()) {
            echo '<h3><a name="ToggleEmailNotification"></a>'.$GLOBALS['Language']->getText('plugin_tracker_include_type','toggle_notification').' '.
            help_button('tracker.html#e-mail-notification').'</h3>';
            echo '
                <p>'.$GLOBALS['Language']->getText('plugin_tracker_include_type','toggle_notif_note').'<br>
                <br><input type="hidden" name="stop_notification" value="0" /> 
                <label class="checkbox"><input id="toggle_stop_notification" type="checkbox" name="stop_notification" value="1" '.(($this->tracker->stop_notification)?'checked="checked"':'').' /> '.
                $GLOBALS['Language']->getText('plugin_tracker_include_type','stop_notification') .'</label>';
        } else if ($this->tracker->stop_notification) {
            echo '<h3><a name="ToggleEmailNotification"></a>'.$GLOBALS['Language']->getText('plugin_tracker_include_type','notification_suspended').' '.
            help_button('tracker.html#e-mail-notification').'</h3>';
            echo '
            <P><b>'.$GLOBALS['Language']->getText('plugin_tracker_include_type','toggle_notif_warn').'</b><BR>';
        }
    }

    protected function displayAdminNotifications_Global(HTTPRequest $request) {
        echo '<h3><a name="GlobalEmailNotification"></a>'.$GLOBALS['Language']->getText('plugin_tracker_include_type','global_mail_notif').' '.
        help_button('tracker.html#e-mail-notification').'</h3>';

        $notifs    = $this->getGlobalNotifications();
        $nb_notifs = count($notifs);
        if ($this->tracker->userIsAdmin()) {
            echo '<p>'. $GLOBALS['Language']->getText('plugin_tracker_include_type','admin_note') .'</p>';
            $id        = 0;
            echo '<table id="global_notifs" class="table table-bordered">';
            echo '<thead><tr>';
            echo '<th><i class="icon-trash"></i></th>';
            echo '<th class="plugin-tracker-global-notifs-people">'. dgettext('tuleap-tracker', 'Notified people') .'</th>';
            echo '<th class="plugin-tracker-global-notifs-updates">'. $GLOBALS['Language']->getText('plugin_tracker_include_type','send_all') .'</th>';
            echo '<th class="plugin-tracker-global-notifs-permissions">'. $GLOBALS['Language']->getText('plugin_tracker_include_type','check_perms') .'</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            $has_notification = (bool)(count($notifs) > 0);

            if (! $has_notification) {
                echo '<tr class="empty-table">';
                echo '<td colspan="4">';
                echo dgettext("tuleap-tracker", "No notification set");
                echo '</td>';
                echo '</tr>';
            } else {
                foreach($notifs as $key => $nop) {
                    $id                = (int)$nop->getId();
                    $addresses         = $nop->getAddresses();
                    $all_updates       = $nop->isAllUpdates();
                    $check_permissions = $nop->isCheckPermissions();
                    echo '<tr>';
                    echo $this->getGlobalNotificationForm($id, $addresses, $all_updates, $check_permissions);
                    echo '</tr>';
                }
            }

            echo '<tr>';
            echo $this->getNewNotificationForm($has_notification);
            echo '</tr>';

            echo '</tbody>';
            echo '</table>';
        } else {
            $ok = false;
            if ( $nb_notifs ) {
                reset($notifs);
                while(!$ok && (list($id,) = each($notifs))) {
                    $ok = $notifs[$id]->getAddresses();
                }
            }
            if ($ok) {
                echo $GLOBALS['Language']->getText('plugin_tracker_include_type','admin_conf');
                foreach($notifs as $key => $nop) {
                    if ($notifs[$key]->getAddresses()) {
                        echo '<div>'. $notifs[$key]->getAddresses() .'&nbsp;&nbsp;&nbsp; ';
                        echo $GLOBALS['Language']->getText('plugin_tracker_include_type','send_all_or_not',($notifs[$key]->isAllUpdates()?$GLOBALS['Language']->getText('global','yes'):$GLOBALS['Language']->getText('global','no')));
                        echo '</div>';
                    }
                }
            } else {
                echo $GLOBALS['Language']->getText('plugin_tracker_include_type','admin_not_conf');
            }
        }
    }

    private function getNewNotificationForm($has_notification)
    {
        $output                   = '';
        $no_notificatoion_class   = '';
        if (! $has_notification) {
            $no_notificatoion_class = 'class="tracker-notification-mail-list-add-in-empty-table"';
        }

        $output .= '<td/>';

        $placeholder = dgettext('tuleap-tracker', 'Enter here a comma separated email addresses list to be notified');

        $output .= "<td $no_notificatoion_class>";
        $output .= '<input class="tracker-global-notification-email" type="text" name="new_global_notification[addresses]" placeholder="'.$placeholder.'")/>';
        $output .= '</td>';

        $output .= '<td class="tracker-global-notifications-checkbox-cell">';
        $output .= '<input type="hidden" name="new_global_notification[all_updates]" value="0" />';
        $output .= '<input type="checkbox" name="new_global_notification[all_updates]" value="1"/>';
        $output .= '</td>';

        $output .= '<td class="tracker-global-notifications-checkbox-cell">';
        $output .= '<input type="hidden" name="new_global_notification[check_permissions]" value="0" />';
        $output .= '<input type="checkbox" name="new_global_notification[check_permissions]" value="1"/>';
        $output .= '</td>';

        return $output;
    }

    protected function getGlobalNotificationForm($id, $addresses, $all_updates, $check_permissions)
    {
        $output  = '';
        $output .= '<td>';
        $output .= '<input type="checkbox" name="remove_global['.$id.']" />';
        $output .= '</td>';
        //addresses
        $output .= '<td>';
        $output .= '<input type="text" class="tracker-global-notification-email" name="global_notification['.$id.'][addresses]" value="'. Codendi_HTMLPurifier::instance()->purify($addresses, CODENDI_PURIFIER_CONVERT_HTML)  .'"/>';
        $output .= '</td>';
        //all_updates
        $output .= '<td class="tracker-global-notifications-checkbox-cell">';
        $output .= '<input type="hidden" name="global_notification['.$id.'][all_updates]" value="0" />';
        $output .= '<input type="checkbox" name="global_notification['.$id.'][all_updates]" value="1" '.($all_updates ? 'checked="checked"' : '').'/>';
        $output .= '</td>';
        //check_permissions
        $output .= '<td class="tracker-global-notifications-checkbox-cell">';
        $output .= '<input type="hidden" name="global_notification['.$id.'][check_permissions]" value="0" />';
        $output .= '<input type="checkbox" name="global_notification['.$id.'][check_permissions]" value="1" '.( $check_permissions ? 'checked="checked"' : '').'/>';
        $output .= '</td>';

        return $output;
    }

    /**
     * this function process global notification data
     * @param Array<Array> $data
     */
    private function processGlobalNotificationDataForUpdate($data)
    {
        $global_notifications = $this->getGlobalNotifications();
        foreach ( $data as $id=>$fields ) {
            if ( empty($fields['addresses']) ) {
                continue;
            }

            if ( !isset($fields['all_updates']) ) {
                continue;
            }

            if ( !isset($fields['check_permissions']) ) {
                continue;
            }

            if ( array_key_exists($id, $global_notifications) ) {
                $this->updateGlobalNotification($id, $fields);
            }
        }
    }

    public function getGlobalNotifications() {
        $notifs = array();
        foreach($this->getGlobalDao()->searchByTrackerId($this->tracker->id) as $row) {
            $notifs[$row['id']] = new Tracker_GlobalNotification($row);
        }
        return $notifs;
    }

    /**
     *
     * @param String $addresses
     * @param Integer $all_updates
     * @param Integer $check_permissions
     * @return Integer last inserted id in database
     */
    protected function addGlobalNotification( $addresses, $all_updates, $check_permissions ) {
        return $this->getGlobalDao()->create($this->tracker->id, $addresses, $all_updates, $check_permissions);
    }

    protected function removeGlobalNotification($id)
    {
        $dao   = $this->getGlobalDao();
        $notif = $dao->searchById($id);

        if (! empty($notif)) {
            $dao->delete($id, $this->tracker->id);
        }
    }

    protected function updateGlobalNotification($global_notification_id, $data) {
        $feedback = '';
        $arr_email_address = preg_split('/[,;]/', $data['addresses']);
        if (!util_validateCCList($arr_email_address, $feedback, false)) {
          $GLOBALS['Response']->addFeedback('error', $feedback);
        } else {
          $data['addresses'] = util_cleanup_emails(implode(', ', $arr_email_address));
          return $this->getGlobalDao()->modify($global_notification_id, $data);
        }
        return false;
    }

    /**
     * @param boolean $update true if the action is an update one (update artifact, add comment, ...) false if it is a create action.
     */
    public function getAllAddresses($update = false) {
        $addresses = array();
        $notifs = $this->getGlobalNotifications();
        foreach($notifs as $key => $nop) {
            if (!$update || $notifs[$key]->isAllUpdates()) {
                foreach(preg_split('/[,;]/', $notifs[$key]->getAddresses()) as $address) {
                    $addresses[] = array('address' => $address, 'check_permissions' => $notifs[$key]->isCheckPermissions());
                }
            }
        }
        return $addresses;
    }

    protected function getGlobalDao() {
        return new Tracker_GlobalNotificationDao();
    }

    protected function getWatcherDao() {
        return new Tracker_WatcherDao();
    }

    protected function getNotificationDao() {
        return new Tracker_NotificationDao();
    }

    public static function isMailingList($email_address) {
        $r = preg_match_all('/\S+\@lists\.\S+/', $subject, $matches);
        if ( !empty($r)  ) {
            return true;
        }
        return false;
    }
}
