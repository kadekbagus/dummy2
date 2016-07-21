<?php
return array( 

    /*
    |--------------------------------------------------------------------------
    | oAuth Config
    |--------------------------------------------------------------------------
    */

    /**
     * Storage
     */
    'storage' => 'OrbitSession', 

    /**
     * Consumers
     */
    'consumers' => array(

        /**
         * Google
         */
        'Google' => array(
        'client_id'     => '79956393015-5lufvh2cjhjn3fi2gm90mve5410qojim.apps.googleusercontent.com',
        'client_secret' => 'U8V4mqhFaD7JNRI0jCGA0Kz9',
        'scope'         => array('userinfo_email', 'userinfo_profile'),
        ),       

    )

);