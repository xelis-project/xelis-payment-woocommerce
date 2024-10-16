<?php

class Xelis_Package
{
  public $version = "v1.15.0";

  public function get_package_url()
  {
    // temporary solution for smaller precomputed table - the default binaries have 26
    return "https://github.com/xelis-project/xelis-payment-woocommerce/releases/download/v0.1.0/xelis_wallet_linux.tar.gz";

    /*
    $os = php_uname('s');
    $cpu = php_uname('m');
    //$prefix = 'https://github.com/xelis-project/xelis-blockchain/releases/latest/download/';

    // use hardcoded xelis version instead of latest link
    // user should update the plugin if we use another version

    $prefix = 'https://github.com/xelis-project/xelis-blockchain/releases/download/' . $this->version . "/";

    if ($os === 'WINNT') {
      if ($cpu == "x86_64" || $cpu == "AMD64" || $cpu == "x86")
        return $prefix . "x86_64-pc-windows-gnu.zip";
    }

    if ($os === "Linux") {
      if ($cpu === "x86_64") {
        return $prefix . "x86_64-unknown-linux-gnu.tar.gz";
      }

      if ($cpu === "aarch64") {
        return $prefix . "aarch64-unknown-linux-gnu.tar.gz";
      }
    }

    throw new Exception("XELIS is not available for your operating system.");
    */
  }

  public function install_package()
  {
    $xelis_folder = __DIR__ . '/xelis_pkg';
    $xelis_zip_file = __DIR__ . '/xelis_zip';

    if (!is_dir($xelis_folder)) {
      if (!mkdir($xelis_folder)) {
        throw new Exception("Failed to create xelis_pkg folder.");
      }
    }

    $xelis_zip_url = $this->get_package_url();
    $pkg_content = fopen($xelis_zip_url, 'r');
    if ($pkg_content === false) {
      throw new Exception(json_encode(error_get_last()));
    }

    file_put_contents($xelis_zip_file, $pkg_content);

    $extension = pathinfo($xelis_zip_url, PATHINFO_EXTENSION);

    if ($extension === 'zip') {
      $zip = new ZipArchive();
      if ($zip->open($xelis_zip_file) == true) {
        if (!$zip->extractTo($xelis_folder)) {
          throw new Exception('Failed to extract XELIS package.');
        }

        // TODO strip first folder like --strip-components=1 in tar

        $zip->close();
      } else {
        throw new Exception('Unable to open XELIS package.');
      }
    } else if ($extension === 'gz') {
      // --strip-components=1 is used to remove subfolder inside the zip
      $command = "tar -xzvf " . escapeshellarg($xelis_zip_file) . " --strip-components=1 -C " . escapeshellarg($xelis_folder);
      //throw new Exception($command);
      exec($command);
    } else {
      throw new Exception('Unknown extension package.');
    }
  }
}