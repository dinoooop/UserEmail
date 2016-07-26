<?php

class UserEmail {

    private $link_life = 3600; //1 houer
    private $link_life_in_word = 'One hour';
    private $company_name = 'AuditionWorld';
    private $alias;

    function __construct() {

        $this->url_reset_password = url('reset-password');
        $this->url_confirm_register = url('confirm-email');
        $this->url_password_changed = url('password-changed');


        // to create link
        $this->alias = array(
            'salt' => 'hio',
            'email' => 'mnp',
            'rand' => 'nbhs',
        );
    }

    function rp_submit_email($input) {

        if (!isset($input['email'])) {
            return false;
        }

        $user = $this->is_email_exist($input['email']);

        if ($user) {

            $this->set_user($user);
            $this->send_mail('password_reset_link');
        } else {
            return false;
        }
    }

    function rp_create_reset_form() {

        $user = $this->verify_the_link();

        if ($user) {
            $this->set_user($user);
            if ($this->check_link_life()) {
                return $this->prepare_password_reset_form();
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    function rp_submit_reset_form() {

        $user = $this->verify_the_link();

        if ($user) {
            $this->set_user($user);

            if ($this->check_link_life()) {

                $input = Input::all();

                if ($this->update_password($input)) {
                    $this->send_mail('password_reset_success');
                    $this->create_salt();
                    return $user;
                }
            }
            return false;
        } else {
            return false;
        }
    }

    function check_user_confirmation() {

        // @execute the script when user press confirmation link from email

        $user = $this->verify_the_link();

        if ($user) {

            // user identified successfully from the link

            $this->set_user($user->id);

            if ($user->verified == 0) {
                $this->change_verification_status(true);

                $this->send_mail('welcome_email');
            }

            return $user;
        }

        return false;
    }

    function prepare_password_reset_form() {

        return array(
            'user' => $this->user,
            'action_url' => $this->url_password_changed,
            'salt' => array(
                'key' => $this->alias['salt'],
                'value' => $this->encrypt($this->user->salt),
            ),
            'email' => array(
                'key' => $this->alias['email'],
                'value' => $this->encrypt($this->user->email),
            ),
            'rand' => array(
                'key' => $this->alias['rand'],
                'value' => rand(2536, 20000000),
            ),
        );
    }

    function is_email_exist($email) {

        $user = User::where('email', $email)
                ->where('verified', 1)
                ->first();
        return $user;
    }

    function update_password($input) {

        if (
                isset($input['password']) &&
                isset($input['confirm_password']) &&
                ($input['password'] == $input['confirm_password'])
        ) {
            $this->user->password = Hash::make($input['password']);
            $this->user->save();
            return true;
        }

        return false;
    }

    function get_reset_form() {
        $result = array(
            'user_id' => $this->user->id,
        );
    }

    function check_link_life() {

        $now = time();
        $salt_time = $this->user->salt;

        if (($now - $salt_time) > $this->link_life) {
            // link life expired
            return false;
        } else {
            return true;
        }
    }

    function set_user($param) {
        if (is_numeric($param)) {
            $this->user = User::find($param);
        } else {
            $this->user = $param;
        }
    }

    function encrypt($enc) {
        $enc = $enc . 'FghTy56klMnq3thum5nimgj';
        $enc = base64_encode($enc);
        $enc = urldecode($enc);
        return $enc;
    }

    function decript($dec) {
        $dec = urldecode($dec);
        $dec = base64_decode($dec);
        $dec = str_replace('FghTy56klMnq3thum5nimgj', '', $dec);
        return $dec;
    }

    /**
     * 
     * Veryfy the link provided sent to the user
     * and identify the user from it
     * @return object User object or false
     */
    function verify_the_link() {

        $input = Input::all();

        if (
                !isset($input[$this->alias['salt']]) ||
                !isset($input[$this->alias['email']]) ||
                !isset($input[$this->alias['rand']])
        ) {
            return false;
        }


        $values = array(
            'salt' => $this->decript($input[$this->alias['salt']]),
            'email' => $this->decript($input[$this->alias['email']]),
            'rand' => $this->decript($input[$this->alias['rand']]),
        );

        $user = User::where('email', $values['email'])
                ->where('salt', $values['salt'])
                ->first();

        $this->set_user($user);

        return ($this->check_link_life() && isset($user->id)) ? $user : false;
    }

    function change_verification_status($do_verification = true) {

        if ($do_verification) {

            // set verified to one

            if ($this->user->verified == 0) {
                $this->user->verified = 1;
                $this->user->save();
            }
        } else {

            // set verified to zero

            if ($this->user->verified == 1) {
                $this->user->verified = 0;
                $this->user->save();
            }
        }
    }

    function create_salt() {
        $salt = array(
            'salt' => time(),
            'email' => $this->user->email,
            'rand' => rand(1000, 10000000),
        );

        $this->user->salt = $salt['salt'];
        $this->user->save();

        return $salt;
    }

    function create_link($type) {

        $salt_input = $this->create_salt();

        $salt = array(
            'salt' => $this->encrypt($salt_input['salt']),
            'email' => $this->encrypt($salt_input['email']),
            'rand' => $this->encrypt($salt_input['rand']),
        );


        $param = $this->alias['salt'] . '=' . $salt['salt'] . '&'
                . $this->alias['email'] . '=' . $salt['email'] . '&'
                . $this->alias['rand'] . '=' . $salt['rand'];

        switch ($type) {

            case 'password_reset_link' :
                $domain = $this->url_reset_password;
                break;

            case 'confirm_register':
                $domain = $this->url_confirm_register;
                break;
        }

        return (isset($domain)) ? $domain . '?' . $param : false;
    }

    // Emails


    function send_mail($type) {

        $data = $this->emails($type);

        Mail::send($data['template'], $data, function($message) use($data) {
            $message->to($data['to'], $data['name'])->subject($data['subject']);
        });
    }

    function emails($type) {

        switch ($type) {
            // Create an email for the user to verify their email-id.
            case 'confirm_register':
                $email = array(
                    'subject' => 'Confirm your email',
                    'template' => 'emails.user.confirm_register',
                    'email_confirmation_link' => $this->create_link('confirm_register'),
                );

                break;

            // Registration success (email verified) send a welcom mail
            case 'welcome_email':
                $email = array(
                    'subject' => 'Welcome to ' . $this->company_name,
                    'template' => 'emails.user.welcome_email',
                );
                break;

            case 'password_reset_link':
                $email = array(
                    'subject' => 'Reset your password',
                    'template' => 'emails.user.password_reset_link',
                    'password_reset_link' => $this->create_link('password_reset_link'),
                    'link_expiry_time' => $this->link_life_in_word,
                );
                break;

            case 'password_reset_success':

                $email = array(
                    'subject' => 'Password successfully changed',
                    'template' => 'emails.user.password_reset_success',
                );
                break;
        }

        if (isset($email)) {
            $same = array(
                'to' => $this->user->email,
                'name' => $this->user->name,
                'company_name' => $this->company_name,
            );


            return array_merge($same, $email);
        } else {
            return false;
        }
    }

}
