<?php
// useractions.php -- HotCRP helpers for user actions
// HotCRP is Copyright (c) 2008-2017 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

class UserActions {
    static private function modify_password_mail($where, $dopassword, $sendtype, $ids) {
        $j = (object) array("ok" => true);
        $result = Dbl::qe("select * from ContactInfo where $where and contactId ?a", $ids);
        while ($result && ($Acct = Contact::fetch($result))) {
            if ($dopassword)
                $Acct->change_password(null, null, Contact::CHANGE_PASSWORD_NO_CDB);
            if ($sendtype && !$Acct->disabled)
                $Acct->sendAccountInfo($sendtype, false);
            else if ($sendtype)
                $j->warnings[] = "Not sending mail to disabled account " . htmlspecialchars($Acct->email) . ".";
        }
        return $j;
    }

    static function disable($ids, $contact) {
        global $Conf;
        $old_nerrors = Dbl::$nerrors;
        $enabled_cids = Dbl::fetch_first_columns("select contactId from ContactInfo where contactId ?a and disabled=0 and contactId!=?", $ids, $contact->contactId);
        if ($enabled_cids)
            Dbl::qe("update ContactInfo set disabled=1 where contactId ?a", $enabled_cids);
        if (Dbl::$nerrors > $old_nerrors)
            return (object) ["error" => true];
        else if (!count($enabled_cids))
            return (object) ["ok" => true, "warnings" => ["Those accounts were already disabled."]];
        else {
            $Conf->save_logs(true);
            foreach ($enabled_cids as $cid)
                $Conf->log_for($contact, $cid, "Account disabled");
            $Conf->save_logs(false);
            return (object) ["ok" => true];
        }
    }

    static function enable($ids, $contact) {
        global $Conf;
        $old_nerrors = Dbl::$nerrors;
        Dbl::qe("update ContactInfo set disabled=1 where contactId ?a and password='' and contactId!=?", $ids, $contact->contactId);
        $disabled_cids = Dbl::fetch_first_columns("select contactId from ContactInfo where contactId ?a and disabled=1 and contactId!=?", $ids, $contact->contactId);
        if ($disabled_cids)
            Dbl::qe("update ContactInfo set disabled=0 where contactId ?a", $disabled_cids);
        if (Dbl::$nerrors > $old_nerrors)
            return (object) ["error" => true];
        else if (!count($disabled_cids))
            return (object) ["ok" => true, "warnings" => ["Those accounts were already enabled."]];
        else {
            $Conf->save_logs(true);
            foreach ($disabled_cids as $cid)
                $Conf->log_for($contact, $cid, "Account enabled");
            $Conf->save_logs(false);
            return self::modify_password_mail("password='' and contactId!=" . $contact->contactId, true, "create", $disabled_cids);
        }
    }

    static function reset_password($ids, $contact) {
        global $Conf;
        return self::modify_password_mail("contactId!=" . $contact->contactId, true, false, $ids);
        $Conf->confirmMsg("Passwords reset. To send mail with the new passwords, <a href='" . hoturl_post("users", "modifygo=1&amp;modifytype=sendaccount&amp;pap[]=" . (is_array($ids) ? join("+", $ids) : $ids)) . "'>click here</a>.");
    }

    static function send_account_info($ids, $contact) {
        global $Conf;
        return self::modify_password_mail("true", false, "send", $ids);
        $Conf->confirmMsg("Account information sent.");
    }

    static function save_clickthrough(Contact $user, Qrequest $qreq) {
        global $Now;
        $confirmed = false;
        if ($qreq->clickthrough_accept && $qreq->clickthrough_sha1) {
            $user->merge_and_save_data(["clickthrough" => [$qreq->clickthrough_sha1 => $Now]]);
            $confirmed = true;
        } else if ($qreq->clickthrough_decline)
            Conf::msg_error("You can’t continue until you accept these terms.");
        if ($qreq->ajax)
            json_exit(["ok" => $confirmed]);
        SelfHref::redirect($qreq);
    }
}
