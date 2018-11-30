<?php
/**
 * Created by PhpStorm.
 * User: matteo
 * Date: 12/09/18
 * Time: 13:06
 */

namespace Features\Aligner\Utils;

use Exceptions\ValidationError;

class AlignUtils
{
    /**
     *
     * Code almost cloned from CatUtils::placehold_xliff_tags()
     *
     * @param $segment
     * @param $lang
     * @return null|string|string[]
     */
    public static function _cleanSegment($segment, $lang) {

        $tagsRegex = [
            '|(</x>)|si',
            '|<(g\s*id=["\']+.*?["\']+\s*[^<>]*?)>|si',
            '|<(/g)>|si',
            '|<(x .*?/?)>|si',
            '#<(bx[ ]{0,}/?|bx .*?/?)>#si',
            '#<(ex[ ]{0,}/?|ex .*?/?)>#si',
            '|<(bpt\s*.*?)>|si',
            '|<(/bpt)>|si',
            '|<(ept\s*.*?)>|si',
            '|<(/ept)>|si',
            '|<(ph .*?)>|si',
            '|<(/ph)>|si',
            '|<(it .*?)>|si',
            '|<(/it)>|si',
            '|<(mrk\s*.*?)>|si',
            '|<(/mrk)>|si'
        ];

        foreach ($tagsRegex as $regex) {
            $segment = preg_replace($regex, '', $segment);
        }

        return $segment;
    }

    public static function _mb_str_replace($search, $replace, $subject) {
        if(is_array($subject)) {
            $ret = array();
            foreach($subject as $key => $val) {
                $ret[$key] = mb_str_replace($search, $replace, $val);
            }
            return $ret;
        }

        foreach((array) $search as $key => $s) {
            if($s == '' && $s !== 0) {
                continue;
            }
            $r = !is_array($replace) ? $replace : (array_key_exists($key, $replace) ? $replace[$key] : '');
            $pos = mb_strpos($subject, $s, 0, 'UTF-8');
            while($pos !== false) {
                $subject = mb_substr($subject, 0, $pos, 'UTF-8') . $r . mb_substr($subject, $pos + mb_strlen($s, 'UTF-8'), 65535, 'UTF-8');
                $pos = mb_strpos($subject, $s, $pos + mb_strlen($r, 'UTF-8'), 'UTF-8');
            }
        }
        return $subject;
    }

    /**
     * @param $segment
     * @param $lang
     * @return float|int
     */
    public static function _countWordsInSegment($segment, $lang) {
        $wordCount = \CatUtils::segment_raw_wordcount( $segment, $lang );

        return $wordCount;
    }


    public static function _getNewOrderValue($first_order, $next_order){
        if($first_order && $next_order){
            return $first_order + ( $next_order - $first_order )/2;
        } else if(!$first_order && !$next_order) {
            return null;
        } else {
            $order = ($first_order == null) ? $next_order/2 : $first_order + Constants::DISTANCE_INT_BETWEEN_MATCHES;
            return $order;
        }
    }

    public static function _mark_xliff_tags($segment) {

        //remove not existent </x> tags
        $segment = preg_replace('|(</x>)|si', "", $segment);

        //$segment=preg_replace('|<(g\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
        $segment = preg_replace('|<(g\s*id=["\']+.*?["\']+\s*[^<>]*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);

        $segment = preg_replace('|<(/g)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);

        $segment = preg_replace('|<(x .*?/?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('#<(bx[ ]{0,}/?|bx .*?/?)>#si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('#<(ex[ ]{0,}/?|ex .*?/?)>#si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(bpt\s*.*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/bpt)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(ept\s*.*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ept)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(ph .*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ph)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(it .*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/it)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(mrk\s*.*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/mrk)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment);

        $segment = self::__encode_tag_attributes( $segment );

        return htmlspecialchars_decode( $segment );
    }

    public static function __encode_tag_attributes( $segment ){

        return preg_replace_callback( '/' . Constants::LTPLACEHOLDER . '(.*?)' . Constants::GTPLACEHOLDER . '/u'
            , function ( $matches ) {
                return Constants::LTPLACEHOLDER . base64_encode( $matches[1] ) . Constants::GTPLACEHOLDER;
            }
            , $segment
        ); //base64 of the tag content to avoid unwanted manipulation

    }

    private static function __decode_tag_attributes( $segment ){

        return preg_replace_callback( '/' . Constants::LTPLACEHOLDER . '(.*?)' . Constants::GTPLACEHOLDER . '/u'
            , function ( $matches ) {
                return Constants::LTPLACEHOLDER . base64_decode( $matches[1] ) . Constants::GTPLACEHOLDER;
            }
            , $segment
        ); //base64 decode of the tag content to avoid unwanted manipulation

    }

    public static function _restore_xliff_tags($segment) {

        $segment = htmlspecialchars($segment);

        $segment = self::__decode_tag_attributes( $segment );

        preg_match_all( '/[\'"]base64:(.+)[\'"]/U', $segment, $html, PREG_SET_ORDER ); // Ungreedy
        foreach( $html as $tag_attribute ){
            $segment = preg_replace( '/[\'"]base64:(.+)[\'"]/U', '"' . base64_decode( $tag_attribute[ 1 ] ) . '"', $segment, 1 );
        }

        $segment = AlignUtils::_mb_str_replace(Constants::LTPLACEHOLDER, "<", $segment);
        $segment = AlignUtils::_mb_str_replace(Constants::GTPLACEHOLDER, ">", $segment);
        return $segment;
    }

    public static function _parseArrayIntegers(array &$array){
        foreach ($array as $key => $value){
            if(is_numeric($array[$key])){
                $array[$key] = (int) $value;
            }
        }
    }

    public static function _getObjectVariables($object){
        if(!is_object($object)){
            return new ValidationError("This function accepts Object-type variables only");
        }
        return array_keys(get_object_vars($object));
    }

    public static function _array_union($x, $y){
        $aunion=  array_merge(
            array_intersect($x, $y),
            array_diff($x, $y),
            array_diff($y, $x)
        );
        return $aunion;
    }

    public static function collectFlashMessages() {
        $currentCookieParams = session_get_cookie_params();

        $prefix = explode('.',$_SERVER['SERVER_NAME'])[0];
        $rootDomain = explode($prefix, $_SERVER['SERVER_NAME'])[1];

        session_set_cookie_params(
            $currentCookieParams["lifetime"],
            $currentCookieParams["path"],
            $rootDomain,
            $currentCookieParams["secure"],
            $currentCookieParams["httponly"]
        );

        \Bootstrap::sessionStart();
        $messages = \FlashMessage::flush() ;
        return $messages ;
    }

}