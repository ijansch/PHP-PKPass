<?php
class PKPass{
	var $certPath;
	var $files = array();
	var $JSON;
	var $SHAs;
	var $certPass = '';

	function setCertificate($path){
		if(file_exists($path)){
			$this->certPath = $path;
		}else{
			die('Error: certificate path is incorrect (file does not exist).');
		}
	}
	function setCertificatePassword($p){
		$this->certPass = $p;
	}
	function setJSON($JSON){
		if(@json_decode($JSON) != false){
			$this->JSON = $JSON;
		}else{
			die('Error: couldn\'t parse JSON string.');
		}
	}
	function addFile($path){
		if(file_exists($path)){
			$this->files[] = $path;
		}else{
			die('Error: file "'.$path.'" does not exist.');
		}
	}
	function create(){
		if(!file_exists('temp/')){
			mkdir('temp');
		}
		$this->SHAs['pass.json'] = sha1($this->JSON);
		foreach($this->files as $file){
			if(stristr(basename($file),'icon') || stristr(basename($file),'logo')){
				$this->SHAs[ucfirst(basename($file))] = sha1(file_get_contents($file));
			}
			$this->SHAs[basename($file)] = sha1(file_get_contents($file));
		}
		$manifest = json_encode((object)$this->SHAs);
		file_put_contents('temp/manifest.json',$manifest);
		exec('openssl pkcs12 -in "'.$this->certPath.'" -clcerts -nokeys -out temp/certificate.pem -passin pass:"'.$this->certPass.'"');
		exec('openssl pkcs12 -in "'.$this->certPath.'" -nocerts -out temp/key.pem -passin pass:"'.$this->certPass.'" -passout pass:"'.$this->certPass.'"');
		exec('openssl smime -binary -sign -signer temp/certificate.pem -inkey temp/key.pem -in temp/manifest.json -out temp/signature -outform DER -passin pass:"'.$this->certPass.'"');
		unlink('temp/certificate.pem');
		unlink('temp/key.pem');
		$zip = new ZipArchive();
		$zip->open("pass.pkpass", ZIPARCHIVE::CREATE);
		$zip->addFile('temp/signature','signature');
		$zip->addFile('temp/manifest.json','manifest.json');
		$zip->addFromString('pass.json',$this->JSON);
		foreach($this->files as $file){
			if(stristr(basename($file),'icon') || stristr(basename($file),'logo')){
				$zip->addFile($file,ucfirst(basename($file)));
			}
			$zip->addFile($file,basename($file));
		}
		$zip->close();
		unlink('temp/signature');
		unlink('temp/manifest.json');
		@rmdir('temp');
		header('Content-Disposition: attachment; filename="pass.pkpass"');
		header('Content-Type: application/vnd.apple.pkpass');
		header('Content-Length: '.filesize("pass.pkpass"));
		echo file_get_contents('pass.pkpass');
		unlink('pass.pkpass');
	}
}
?>