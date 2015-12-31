<?php

class Optimizer {
	public static function tokens_to_code($tokens, $echo = false) {
		$code = '';
		foreach ($tokens as $i => $token) {
			$tokenStr = ($token === (array)$token) ? $token[1] : $token;
			$code .= $tokenStr;
		}
		if ($echo) {
			echo '<pre>', Convert::raw2xml($code), '</pre>';
		}
		return $code;
	}

	public static function get_all_files() {
        $directory_stack = array(BASE_PATH);
        $ignored_files = array();
        $file_list = array();
        while ($directory_stack) {
            $current_directory = array_shift($directory_stack);
            $files = scandir($current_directory);
            foreach ($files as $filename) {
                //  Skip all files/directories with:
                //      - A starting '.'
                //      - A starting '_'
                //      - Ignore 'index.php' files
                $pathname = $current_directory . DIRECTORY_SEPARATOR . $filename;
                if (isset($filename[0]) && (
                    $filename[0] === '.' ||
                    //$filename[0] === '_' ||
                    $filename === 'index.php' ||
                    isset($ignored_files[$pathname])
                )) 
                {
                    continue;
                }
                else if (is_dir($pathname) === TRUE) {
                    $directory_stack[] = $pathname;
                } else if (pathinfo($pathname, PATHINFO_EXTENSION) === 'php') {
                    $file_list[] = $pathname;
                }
            }
        }
        return $file_list;
	}

	public function optimize() {
		foreach (static::get_all_files() as $filename) {
			$filename = 'C:\wamp\www\silverstripe\framework\model\ArrayList.php';
			$fileData = file_get_contents($filename);
			$tokens = token_get_all($fileData);
			//var_dump(token_name(308)); exit;
			//var_dump($tokens); exit;
			$i = 0;
			while (isset($tokens[$i])) {
				$token = $tokens[$i++];
				$tokenId = ($token === (array)$token) ? $token[0] : $token;
				if ($tokenId === T_STRING && $token[1] === 'is_array') {
					$isArrayIndex = $i-1;
					$isArrayParam = '';
					$openBrackets = 0;
					while (isset($tokens[$i]) && ($openBrackets == 0 && $tokenId !== ')')) {
						$token = $tokens[$i];
						unset($tokens[$i]);
						$tokenId = ($token === (array)$token) ? $token[0] : $token;
						if ($tokenId === T_VARIABLE || $tokenId === T_OBJECT_OPERATOR || $tokenId === T_STRING
							|| ($isArrayParam !== '' && $tokenId === '(') || $openBrackets > 0) {
							$isArrayParam .= ($token === (array)$token) ? $token[1] : $token;
							if ($isArrayParam !== '') {
								$openBrackets += (int)($tokenId === '(');
								$openBrackets -= (int)($tokenId === ')');
							}
						}
						$i += 1;
					}
					$tokens[$isArrayIndex] = array(0, '('.$isArrayParam.' === (array)'.$isArrayParam.')', 0);
				}
			}
			$code = self::tokens_to_code($tokens);
			if ($code != $fileData) {
				file_put_contents($filename, $code);

				//var_dump($tokens);
				self::tokens_to_code($tokens, true); var_dump($filename); exit;
			}
		}
	}
}