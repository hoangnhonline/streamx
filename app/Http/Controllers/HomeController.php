<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use Hash, Auth;
use App\Helpers\simple_html_dom;
use App\Helpers\JavascriptUnpacker;
use App\Models\DataVideo;

class HomeController extends Controller
{
    public function index(Request $request){             
        $ax_url = $request->ax_url ? $request->ax_url : null;
        $code = '';
        if($ax_url){
            $this->validate($request,[
                'ax_url' => 'required|url'            
            ],
            [
                'ax_url.required' => 'Please enter URL.',            
                'ax_url.url' => 'URL is invalid.'
            ]);

            $rs = DataVideo::where('origin_url', $ax_url)->first();
            if(!$rs){
                $code = md5($ax_url);             
                DataVideo::create(['origin_url' => $ax_url, 'code' => $code]);
            }else{
                $code = $rs->code;
            }
        }

        return view('index', compact('ax_url', 'code'));
    }
    public function play(Request $request){
        $code = $request->code;
        $rs = DataVideo::where('code', $code)->first();
        $video_url = $poster_url = '';
        if($rs){
            $origin_url = $rs->origin_url;           
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1" );
            curl_setopt( $ch, CURLOPT_URL, $origin_url );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $result = curl_exec($ch);
            //dd($result);
            curl_close($ch);
            if( strpos($origin_url, 'streamable')){
                $tmp = explode('"url": "', $result);               
                $tmp = explode('",', $tmp[1]);
                $video_url = "https:".$tmp[0];    
                $tmpPoster = explode('"thumbnail_url": "', $result);
                $tmp = explode('",', $tmpPoster[1]);
                $poster_url = "https:".$tmp[0];                                      
            }elseif( strpos($origin_url, 'fastplay.to')){                
                $crawler = new simple_html_dom();                
                $crawler->load($result); 
                $js = $crawler->find('script', 7)->innertext;
                $unpack = new JavascriptUnpacker;
                $tmpScript = $unpack->unpack($js);                
                                              
                $tmp = explode('{file:"', $tmpScript);
               
                if(isset($tmp[4])){
                    $tmp = explode('"', $tmp[4]);   
                    $video_url = $tmp[0];                 
                }elseif(isset($tmp[3])){
                    $tmp = explode('"', $tmp[3]);   
                    $video_url = $tmp[0];    
                }elseif(isset($tmp[2])){
                    $tmp = explode('"', $tmp[2]);   
                    $video_url = $tmp[0];    
                }elseif(isset($tmp[1])){
                    $tmp = explode('"', $tmp[1]);   
                    $video_url = $tmp[0];    
                }
                
                $tmpPoster = explode('image:"', $tmpScript);
              
                if(isset($tmpPoster[1])){
                    $tmp = explode('"', $tmpPoster[1]);   
                    $poster_url = $tmp[0];                 
                }
            }else{                                
                $crawler = new simple_html_dom();                
                $crawler->load($result); 
                $js = $crawler->find('script', 4)->innertext;
                $unpack = new JavascriptUnpacker;
                $tmpScript = $unpack->unpack($js);                               
                $tmp = explode('{file:"', $tmpScript);
                if(isset($tmp[1])){
                    $tmp = explode('"', $tmp[1]);   
                    $video_url = $tmp[0];                 
                }

                $tmpPoster = explode('image:"', $tmpScript);
              
                if(isset($tmpPoster[1])){
                    $tmp = explode('"', $tmpPoster[1]);   
                    $poster_url = $tmp[0];                 
                }                
            }            
            return view('play', compact('video_url', 'poster_url'));    
        }else{
            dd('Invalid code');
        }
        
    }    
}
