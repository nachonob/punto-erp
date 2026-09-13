<?php
declare(strict_types=1);

final class OpenAITranscription
{
    public static function apiKey(array $cfg): string
    {
        return trim((string)($cfg['openai_api_key'] ?? getenv('OPENAI_API_KEY') ?: ''));
    }

    public static function transcribe(string $filePath, string $mimeType, array $cfg): string
    {
        $key=self::apiKey($cfg);
        if($key==='') throw new RuntimeException('Falta configurar OPENAI_API_KEY en el servidor.');
        if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL no está habilitado en el servidor.');
        if(!is_file($filePath)) throw new RuntimeException('No se encontró el audio para transcribir.');
        $model=trim((string)($cfg['openai_transcription_model'] ?? 'gpt-4o-mini-transcribe')) ?: 'gpt-4o-mini-transcribe';
        $post=[
            'model'=>$model,
            'file'=>new CURLFile($filePath,$mimeType,basename($filePath)),
            'language'=>'es',
            'prompt'=>'Parte técnico de una empresa de domótica en Argentina. Términos frecuentes: LifeSmart, Control4, Shelly, DEFED, CoSS, domótica, teclas inteligentes, cortinas, persianas, cerraduras, cámaras, climatización, cableado, escenas, hub, sensor, actuador.'
        ];
        $ch=curl_init('https://api.openai.com/v1/audio/transcriptions');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>120]);
        $body=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($body===false||$err!=='') throw new RuntimeException('No se pudo conectar con el servicio de transcripción.');
        $json=json_decode((string)$body,true);
        if($status<200||$status>=300){$message=(string)($json['error']['message']??'Error de transcripción');throw new RuntimeException($message);}
        $text=trim((string)($json['text']??''));if($text==='')throw new RuntimeException('La transcripción volvió vacía.');
        return $text;
    }
}
