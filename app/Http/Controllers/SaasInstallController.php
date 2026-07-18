<?php

namespace App\Http\Controllers;
use App\Http\Requests\InstallationRequest;
use App\Http\Requests\SaasInstallationRequest;
use App\Traits\ENVFilePutContent;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

use Illuminate\Http\Request;

class SaasInstallController extends Controller
{
    use ENVFilePutContent;

    public function saasInstallStep1()
    {
        return view('backend.saas.step_1');
    }

    public function saasInstallStep2()
    {
        return view('backend.saas.step_2');
    }
    public function saasInstallStep3()
    {
        return view('backend.saas.step_3');
    }

    public function saasInstallProcess(SaasInstallationRequest $request)
    {

        $isPurchaseVerified = self::purchaseVerify($request->purchasecode);

        if (!$isPurchaseVerified->codecheck) {
            return redirect()->back()->withErrors(['errors' => ['Wrong Purchase Code !']]);
        }

        $envPath = base_path('.env');
        if (!file_exists($envPath))
            return redirect()->back()->withErrors(['errors' => ['.env file does not exist.']]);
        elseif (!is_readable($envPath))
            return redirect()->back()->withErrors(['errors' => ['.env file is not readable.']]);
        elseif (!is_writable($envPath))
            return redirect()->back()->withErrors(['errors' => ['.env file is not writable.']]);
        else {
            DB::beginTransaction();
            try {

                $data = self::fileReceivedFromAuthorServer($isPurchaseVerified->authorServerURL);
                if(!$data['isReceived']) {
                    throw new Exception("The file transfer has failed. Please try again later.", 1);
                }

                self::fileUnzipAndDeleteManage($data);
                $this->envSetDatabaseCredentials($request);
                self::switchToNewDatabaseConnection($request);
                self::migrateCentralDatabase();
                self::seedCentralDatabase();
                session(['centralDomain' => $request->central_domain]);
                self::optimizeClear();

                DB::commit();

                return redirect($request->central_domain.'/saas/install/step-4');

            } catch (Exception $e) {
                DB::rollback();

                return redirect()->back()->withErrors(['errors' => [$e->getMessage()]]);
            }
        }
    }

    protected static function purchaseVerify(string $purchaseCode) : object
    {
        $url = 'https://saleprosaas.com/public/purchaseverify/';
        $post_string = 'purchasecode='.urlencode($purchaseCode);
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $post_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $object = new \stdClass();
        $object = json_decode(strip_tags($result));
        curl_close($ch);

        return $object;
    }

    protected static function fileReceivedFromAuthorServer(string $authorServerURL): array
    {
        $remoteFileName = pathinfo($authorServerURL)['basename'];
        $localFile = base_path('/'.$remoteFileName);
        $isCopied = copy($authorServerURL, $localFile);

        return [
            'isReceived' => $isCopied,
            'remoteFileName' => $remoteFileName,
        ];
    }

    protected static function fileUnzipAndDeleteManage(array $data)
    {
        if ($data['isReceived']) {

            self::baseDirectoryDelete();
            self::baseFileDelete();

            $zip = new ZipArchive;
            self::unzipAndDeleteProcessing($zip, $data['remoteFileName']);
            self::unzipAndDeleteProcessing($zip, 'saasrest.zip');
       }
    }

    protected static function baseDirectoryDelete(): void
    {
        //Modules folder not deleted
        $baseDirectories = [
            'app',
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
            'storage',
            'tests',
            'track',
            'vendor',
        ];

        foreach ($baseDirectories as $value) {
            $directoryPath = base_path($value);
            File::deleteDirectory($directoryPath);
        }
    }
    protected static function baseFileDelete(): void
    {
        //.env, .htaccess, vendorsaas.zip file not deleted
        $baseFiles = [
            'artisan',
            'composer.json',
            'composer.lock',
            'manifest.json',
            'modules_statuses.json',
            'package.json',
            'package-lock.json',
            'phpunit.xml',
            'README.md',
            'server.php',
            'service-worker.js',
            'webpack.mix.js',
            '.editorconfig',
            '.env.example',
            '.gitattributes',
            '.gitignore',
            '.styleci.yml',
        ];

        foreach ($baseFiles as $file) {
            File::delete(base_path("/$file"));
        }
    }

    protected static function unzipAndDeleteProcessing($zip, string $fileName): void
    {
        $file = base_path($fileName);
        $res = $zip->open($file);
        if ($res === TRUE) {
           $zip->extractTo(base_path());
           $zip->close();

           // ****** Delete Zip File ******
           File::delete($file);
        }
    }

    protected function envSetDatabaseCredentials($request): void
    {
        $centralDomain = self::filterURL($request->central_domain);

        $this->dataWriteInENVFile('CPANEL_API_KEY', $request->cpanel_api_key);
        $this->dataWriteInENVFile('CPANEL_USER_NAME', $request->cpanel_username);
        $this->dataWriteInENVFile('CENTRAL_DOMAIN', $centralDomain);
        $this->dataWriteInENVFile('DB_PREFIX', $request->db_prefix);
        $this->dataWriteInENVFile('DB_CONNECTION', 'saleprosaas_landlord');
        $this->dataWriteInENVFile('DB_HOST', $request->db_host);
        $this->dataWriteInENVFile('DB_PORT', $request->db_port);
        $this->dataWriteInENVFile('DB_DATABASE', null);
        $this->dataWriteInENVFile('LANDLORD_DB', $request->db_name);
        $this->dataWriteInENVFile('DB_USERNAME', $request->db_username);
        $this->dataWriteInENVFile('DB_PASSWORD', $request->db_password);
        //$this->dataWriteInENVFile('VERSION', '1.1.3');

    }

    protected static function filterURL(string $centralDomain): string
    {
        if (strpos($centralDomain, 'http://') === 0) {
            $url = substr($centralDomain, 7);
        }
        elseif (strpos($centralDomain, 'https://') === 0) {
            $url = substr($centralDomain, 8);
        }

        return $url = rtrim($url, '/');
    }

    public function switchToNewDatabaseConnection($request): void
    {
        DB::purge('mysql');
        Config::set('database.connections.mysql.host', $request->db_host);
        Config::set('database.connections.mysql.database', $request->db_name);
        Config::set('database.connections.mysql.username', $request->db_username);
        Config::set('database.connections.mysql.password', $request->db_password);
    }

    protected static function migrateCentralDatabase(): void
    {
        Artisan::call('migrate --path=database/migrations/landlord'); 
    }

    protected static function seedCentralDatabase(): void
    {
        Artisan::call('db:seed');
    }

    protected static function optimizeClear(): void
    {
        Artisan::call('optimize:clear');
    }

    public function saasInstallStep4()
    {
        return view('backend.saas.step_4');
    }
}
