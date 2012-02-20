<?php 

/* CONFIGURATION */ 
$path = empty($_GET['path']) ? '' : $_GET['path']; // Path to your videofiles (leave the trailing slash). Leave empty if script is 
            // within the same directory as your videofiles. 
/* Nothing below this line needs configuring */ 
$seekat = $_GET["position"];
$filename = htmlspecialchars($_GET['file']); 
$ext = strrchr($filename, ".");
$file = $path . $filename;
/* функция поиска с параметрами.*/

    function arr_sch( $haystack, $needle )
    {
        // тут надо написать алгоритм половинного деления.
        foreach ($haystack as $i => $value) {
            if (($haystack[$i] > $needle) && ($haystack[$i-1] <= $needle) && ($i>0)) {
                return ($i-1);
                break;
            }
        }
    }

    if ((file_exists($file)) && ($ext==".flv") && (strlen($filename)>2) && (!eregi(basename($_SERVER['PHP_SELF']), $filename)) && (ereg('^[^./][^/]*$', $filename))) 
    {
        // проверяем существует ли кэш файл с метаданными 
        // нужно поменять местаvb для проврки даты создания файла (для того чтобы убедиться он ли это)
        if (!file_exists("ch_flv_meta/".md5($file))){
        
            /* This code is taken from the flv4php project (from the file called "test2.php") */ 
            define('AUDIO_FRAME_INTERVAL', 3); 
            include_once 'flv/FLV.php'; 

            $flv = new FLV(); 

            try { 
                $flv->open( $file ); 
            } catch (Exception $e) { 
                die("<pre>The following exception was detected while trying to open a FLV file:\n" . $e->getMessage() . "</pre>"); 
            } 

            $meta = array(); 
            $meta['keyframes'] = array(); 
            $meta['keyframes']['filepositions'] = array(); 
            $meta['keyframes']['times'] = array(); 

            $skipTagTypes = array(); 

            try { 
                while ($tag = $flv->getTag( $skipTagTypes )) 
                { 
                    $ts = floor($tag->timestamp/1000);  // округляем полученно при делении в меньшую сторону

                    if ($tag->timestamp > 0) 
                        $meta['lasttimestamp'] = $ts; 

                    switch ($tag->type) 
                    { 
                        // оставляем сбор только данных о видео и метках.
                        case FLV_Tag::TYPE_VIDEO : 

                            //Optimization, extract the frametype without analyzing the tag body 
                            if ((ord($tag->body[0]) >> 4) == FLV_Tag_Video::FRAME_KEYFRAME) 
                            { 
                                $meta['keyframes']['filepositions'][] = $flv->getTagOffset(); 
                                $meta['keyframes']['times'][] = $ts; 
                            } 

                        break; 
                    } 

                    //Does it actually help with memory allocation? 
                    unset($tag); 
                } 
            } 
            catch (Exception $e) 
            { 
                echo "<pre>The following error took place while analyzing the file:\n" . $e->getMessage() . "</pre>"; 
                $flv->close(); 
                die(1); 
            }

            $flv->close(); 

            // последний 
            if (! empty($meta['keyframes']['times'])) 
                $meta['lastkeyframetimestamp'] = $meta['keyframes']['times'][ count($meta['keyframes']['times'])-1 ]; 

            // полная продолжительность
            $meta['duration'] = $meta['lasttimestamp']; 
            
            $handle = fopen("ch_flv_meta/".md5($file), "w");
            if (fwrite($handle, serialize($meta)) === FALSE) { echo "Не могу произвести запись в файл ($filename)"; exit; }
            fclose($handle);    
        }else{
            $meta ='';
            $handle = fopen("ch_flv_meta/".md5($file), "r");
            while (!feof($handle)) {
                $meta .= (fread($handle, 10000));
            }
            $meta = unserialize($meta); 
            fclose($handle);
        }
        // Вычисляем по секунде ключевой кадр
        //$fileposition = array_search(number_format($seekat), $meta['keyframes']['times']); // 
        $fileposition = arr_sch($meta['keyframes']['times'], $seekat);

            
        $fp1 = (int)$meta['keyframes']['filepositions'][$fileposition]; 
        $fp2 = (int)$meta['keyframes']['filepositions'][$fileposition+1]; 

        $startReading = $fp1; // PHP has to start reading the file from this number of bytes here 
        $readLength = $fp2 - $fp1; // The length (number of bytes) PHP needs to read until the next keyframe 

        // This code is taken from "flvprovider.php" (some small changes by sander1) 
        header("Content-Type: video/x-flv"); 
        if ($startReading != 0) 
        { 
            print("FLV"); 
            print(pack('C', 1 )); 
            print(pack('C', 1 )); 
            print(pack('N', 9 )); 
            print(pack('N', 9 )); 
        } 
        $fh = fopen($file, "rb");
        fseek($fh, $startReading);
        while (!feof($fh)) {
          print (fread($fh, 10000));
        }
        fclose($fh);

    } 
    else 
    { 
        print("<pre>Error: The file does not exist</pre>"); 
    }
    
?>