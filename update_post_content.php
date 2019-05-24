<?php

/**
* This script grabs a bunch of IDs and runs a bunch of search and replace stuff
*/

$db_host = 'localhost';
$db_name = 'REDACTED';
$db_user = 'REDACTED';
$db_pass = 'REDACTED';

try{
    $db =  new PDO('mysql:host=localhost;dbname=REDACTED', $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    print "It worked!" . "\n";
} catch (PDOException $e) {
    print "DB Connection error: " . $e->getMessage() . "\n";
    die();
}

// Create an array of IDs

// Get all the IDs in a PDO querystring
$ids =  $db->query('SELECT ID FROM wplz_posts WHERE post_content LIKE "%item=\"174\"%" AND post_type="page" AND post_title LIKE "%AZ-%"');
// Iterate through the querystring and create an array
$id_array = array();
foreach( $ids as $id) {
        array_push($id_array, $id[0]);
}

// for each id in the array, store post_content into a variable and run some replacements
// then update post_content with the modified content
foreach ( $id_array as $id ) {
 $query = 'SELECT post_content FROM wplz_posts WHERE ID = ';
        $query .= $id;
        $contents = $db->query($query);

        $final_content = "";
        foreach( $contents as $content ) {
                $final_content = $content[0];
        }

        $output=preg_replace('/961,960,959,913,912,911,910,909,908,907/',
                             '8661,8667,8660,8664,8665,8663,8662,8668,8666,8669',
                             $final_content );
        $output=preg_replace('/4566/', '8678', $output);
        $output=preg_replace('/174/', '8656', $output);
        $output=preg_replace('/174/', '8678', $output);
        
        $statement = 'UPDATE wplz_posts SET post_content = ' . "'" .  $output . "'"  . ' WHERE wplz_posts.ID = ' . $id . ';';
      
        $update = $db->prepare($statement);
        $update->execute();
}                                                                                                                2,0-1         Top
