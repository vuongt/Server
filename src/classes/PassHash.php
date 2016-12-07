<?php

class PassHash {

    // blowfish
    private static $algo = '$2a';
    // cost parameter
    private static $cost = '$10';

    public static function unique_salt() {
        return substr(sha1(mt_rand()), 0, 22);
    }

    /**
     * @param $password
     * @return string the hashed pass
     * generate a hassed password
     */
    public static function hash($password) {

        return crypt($password, self::$algo .
                self::$cost .
                '$' . self::unique_salt());
    }

    /**
     * @param $hash
     * @param $password
     * @return bool
     * conapare a password to a hashed password
     */
    public static function check_password($hash, $password) {
        $full_salt = substr($hash, 0, 29);
        $new_hash = crypt($password, $full_salt);
        return ($hash == $new_hash);
    }

}

?>