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
        $directory_stack = array(BASE_PATH.DIRECTORY_SEPARATOR.'framework');
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
                    $filename === 'main.php' ||
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
		//return;
		foreach (static::get_all_files() as $filename) {
			$fileData = file_get_contents($filename);
			$tokens = token_get_all($fileData);
			$i = -1;
			while (isset($tokens[++$i])) {
				$topI = $i;
				$topToken = $token = $tokens[$topI];
				$tokenId = ($token === (array)$token) ? $token[0] : $token;
				if ($tokenId === T_STRING && ($topToken[1] === 'is_array' || $topToken[1] === 'is_string')) {
					// Skip unnecessary characters before the '(' token such as whitespace
					$token = $tokens[++$i];
					$tokenId = ($token === (array)$token) ? $token[0] : $token;
					if ($tokenId === T_WHITESPACE) {
						// If this token is whitespace, skip to the next, assumed to be (
						$token = $tokens[++$i];
						$tokenId = ($token === (array)$token) ? $token[0] : $token;
					}

					if ($tokenId !== '(') {
						trigger_error('Unexpected token.');
						exit;
					}

					// Store all code until hits the end ')', supports brackets inside function
					// call for the 'func_get_arg(1)' cases.
					$openBrackets = 0;
					$funcParamCode = '';
					while(true) {
						$token = $tokens[++$i];
						$tokenId = ($token === (array)$token) ? $token[0] : $token;
						if ($openBrackets == 0 && $tokenId === ')') {
							++$i;
							break;
						}
						$openBrackets += (int)($tokenId === '(');
						$openBrackets -= (int)($tokenId === ')');
						if (isset($token[1])) {
							$funcParamCode .= $token[1];
						} else {
							$funcParamCode .= $token;
						}
					};
					
					// Keep top token to override and remove the rest
					for ($j = $topI+1; $j < $i; ++$j) {
						unset($tokens[$j]);
					}

					switch ($topToken[1])
					{
						case 'is_array':
							$tokens[$topI] = array(0, '('.$funcParamCode.' === (array)'.$funcParamCode.')', 0);
						break;

						case 'is_string':
							$tokens[$topI] = array(0, '('.$funcParamCode.' === (string)'.$funcParamCode.')', 0);
						break;

						default:
							trigger_error('Unexpected switch case '.$topToken[1]);
							exit;
						break;
					}
				}
			}
			$code = self::tokens_to_code($tokens);
			if ($code != $fileData) {
				file_put_contents($filename, $code);

				//var_dump($tokens);
				//self::tokens_to_code($tokens, true); var_dump($filename); exit;
			}
			//break;
		}
	}
}