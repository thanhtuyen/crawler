<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Crawler extends CI_Controller {

    /**
     * Handle the connection from CURL
     * @var
     */
    private $_ch;

    /**
     * The root path of crawler
     * @var
     */
    private $_rootPath;

    /**
     * Config object
     * Load from the config.txt file
     * @var
     */
    private $_config ;

    function __construct() {
        parent::__construct();
        $this->_rootPath = FCPATH;
        $configFile = $this->_rootPath.'config.txt';
        $this->_config = $this->getConfigFile($configFile);
    }

    private function getConfigValue($key) {
        if (isset($this->_config[$key]))
            return $this->_config[$key];
        return false;
    }

    private function error($message, $exit = true) {
        echo $message.'<br>';
        $this->log('Error: '.$message);
        if ($exit) exit();
    }

    private function login(){
        //username and password of account
        $username = $this->getConfigValue('username');
        if (empty($username)) {
            $this->error("Please set the username in config file");
        }

        $password = $this->getConfigValue('password');
        if (empty($password)) $this->error("Please set password to config file");

        $loginPageUrl = "https://www.jobbkk.com/login/employer/login_page";
        $loginActionUrl="https://www.jobbkk.com/login/employer/dologin";
        //set the directory for the cookie using defined document root var
        $path = $this->_rootPath."tmp";
        //login form action url
        $postinfo = "username_emp=".$username."&password_emp=".$password;
        $cookie_file_path = $path."/cookie.txt";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_URL, $loginActionUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
        //set the cookie the site has for certain features, this is optional
        curl_setopt($ch, CURLOPT_COOKIE, "cookiename=0");
        curl_setopt($ch, CURLOPT_USERAGENT,
            "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.7.12) Gecko/20050915 Firefox/1.0.7");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_REFERER, $loginPageUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postinfo);
        $html = curl_exec($ch);
        $this->_ch = $ch;
        $html = substr($html,3,strlen($html)); // because the respond have some strange charaters
        $result = json_decode($html);
        if ($result->query ==1 )
            return true;
        return false;

    }

    /**
     * Change language to English
     */
    private function setLanguage(){
        $url = "https://www.jobbkk.com/global/language/change_lang/en";
        curl_setopt($this->_ch, CURLOPT_URL, $url);
        curl_exec($this->_ch);
    }

    /**
     * Get detail of CV
     * @param $link
     * @return mixed
     */
    private function getHtml($link){
        curl_setopt($this->_ch, CURLOPT_URL, $link);
        $html = curl_exec($this->_ch);
        return $html;
    }

    /**
     * Close the connection when finished
     */
    private function closeCurl(){
        curl_close($this->_ch);
    }

    private function getFilePath(){
        return $this->_rootPath.'tmp/';
    }


    /**
     * Write to log file
     * them same name and folder with the csv file
     * @param $message
     */
    private function log($message){
        if ($this->getConfigValue('logMode')==1) {
            $csvFolder = $this->getConfigValue('csvFolder');
            $fileLog = $csvFolder.'/'.date("d-m-Y", time()).'.log';
            $chmode = false;
            if (!file_exists($fileLog)) {
                $chmode = true;
            }
            $message = date('d-m-Y h:i:s', time()) .'   '. $message. PHP_EOL;
            file_put_contents($fileLog, $message, FILE_APPEND);
            if ($chmode) {
                chmod($fileLog, 0664);
            }
        }
    }

    private function removeSpace($str, $removeNewLine = false) {
        if ($removeNewLine) {
            $str = preg_replace("/\n(\s*\n)+/", " ", $str);
        }else {
            $str = preg_replace("/\n(\s*\n)+/", "\n", $str);
        }
        $result = preg_replace('/ +/', ' ',$str);
        return $result;

    }

    public function run(){
        $page = $this->getCurrentPage();
        // if current day is first of the month
        // then reset $page to get from the begining again
        if (date('d', time())==1 ){
            $page = 1;
        }
        $this->log("Start to get page: ". $page);
        $limit = $this->getConfigValue('limitPerRequest');
        $csvFolder = $this->getConfigValue('csvFolder');
        if (empty($csvFolder ))
            $this->error("Please set CSV Folder in config file");
        if (empty($limit))
            $this->error('Please set limit per request');
        if ($this->login()){

            $this->setLanguage();
            $count  = 0;
            $csvFileName = $csvFolder.'/'.date("Y-m-d", time()).'.csv';
            $this->log("CSV file: ".$csvFileName);
            $this->log("Limit: ".$limit);
            while ($count <=$limit) {
                $links = $this->getListDetailCvLinks($page);
                // raise the count up in case the links is empty
                if (count($links) == 0) {
                    $count ++;
                    $this->log("The page: ".$page." don't have any link to get");
                    continue;
                }
                $csvObjectArray = array();
                $totalOkayCv = 0;
                foreach ($links as $link) {
                    $this->log("Get CV from : ".$link);
                    $html = $this->getHtml($link);
                    $objCv = $this->getDetailCVObject($html, $link);
                    if ($this->isOkayCv($objCv)) {
                        $csvObjectArray[] = $objCv;
                        $totalOkayCv ++ ;
                    }else {
                        $this->log("Can not export CV from ".$link);
                    }
                    $count ++;

                }
                $this->log("Total CV from page ".$page.' is '.$totalOkayCv);
                $this->exportCsv($csvObjectArray, $csvFileName);
                $this->log("Saved ".count($csvObjectArray).' CV to file');
                $page ++ ;
                $this->log("Next page : ". $page);

            }
            // save current page
            $this->setCurrentPage($page);
            $this->closeCurl();
        }else {
            $this->error("Can not login!");
        }
    }

    private function isOkayCv($cvObject) {
        if (empty($cvObject['name']) && empty($cvObject['email']) &&
            empty($cvObject['gender'])
        ) {
            return false;
        }
        return true;
    }

    /**
     * Get detail link in page
     * Don't need to login first
     * @param $page
     */
    private function getListDetailCvLinks($page){
        $link = "https://www.jobbkk.com/resumes/lists/";
        $pageLink = $link.$page;
        $pageHtml = $this->getHtml($pageLink);
        $xPath = '//*[@id="box_left1"]/div[2]/div/a/@href';
        $elements = $this->getElement($xPath, $pageHtml);
        $links = array();
        foreach ($elements as $el) {
            if ($el->value=="https://www.jobbkk.com/mobile/promote") {
                continue;
            }
            $links [] = $el->value;// this is the detail link
        }
        return $links;
    }

    /**
     * Get elements by id, tag or xPath
     * @param $el
     * @param $html
     * @param bool|true $s
     * @return DOMElement|DOMNode|DOMNodeList|null
     */
    private function getElement($el,$html,$s=true){
        try{
            libxml_use_internal_errors(true);
            $doc = new DOMDocument;
            $doc->preserveWhiteSpace = false;
            $doc->loadHTML($html);
            $t= substr($el,0,1);
            switch($t){
                case '.':
                    $els = $doc->getElementsByTagName(str_replace('.','',$el));
                    if($s) return $els->item(0);
                    return $els;
                    break;
                case '#' :
                    $ele = $doc->getElementById(str_replace('#','',$el));
                    return $ele;
                    break;
                default :
                    $xpath = new DOMXPath($doc);
                    return $xpath->query($el);
            }
        }
        catch(Exception $e){
            return null;
        }
    }



    /**
     * get value from div for Jobcategory
     * @param $el
     * @return array|bool
     */

    private function getObjectElementJobRequirment($el)
    {
        if ($el == null) return false;
        // job category 1 is exist
        $categoryValue = trim(@$el->childNodes[1]->childNodes[3]->nodeValue);
        $jobPosition = trim(@$el->childNodes[3]->childNodes[3]->nodeValue);
        return array( "Value1"=>$categoryValue, 'Value2' => $jobPosition);
    }

    /**
     * get content from html
     * @param $element
     * @param $pageHtml
     * @return array
     */

    private function processGetContent($element, $pageHtml){
        $els = $element['elements'];
        $data = array();
        switch($element['key']) {
            case  "fullName":
                foreach($element['elements'] as $el) {
                    if($el->nodeValue) {
                        $value = explode(".", $el->nodeValue);
                        $data['title'] = $value[0];
                        $data['name'] = $value[1];
                    }else {
                        $data['title'] = '';
                        $data['name'] = '';
                    }
                }
                return $data;
                break;
            case  "profile":

                //gender
                $objectGender = $this->getContentDiv($els, "Gender");
                $gender = @$objectGender->childNodes[1]->nodeValue;
                $data["gender"] = trim($gender);

                //birthday
                $objectBirthday = $this->getContentDiv($els, "Date of Birth");
                $birthday = @$objectBirthday->childNodes[1]->nodeValue;
                $data["birthday"] = trim($birthday);

                //age
                $age = @$objectBirthday->childNodes[4]->nodeValue;
                $data['age'] = trim($age);

                //marital status
                $objectMarial = $this->getContentDiv($els, "Marital Status");
                $marialStatus = @$objectMarial->childNodes[2]->nodeValue;
                $data["marital_status"] = trim($marialStatus);

                //malitary status
                $objectMalitary = $this->getContentDiv($els, "Malitary Status");
                $malitary = @$objectMalitary->childNodes[2]->nodeValue;
                $data["malitary_status"] = trim($malitary);

                //national
                $objectNational = $this->getContentDiv($els, "Nationality");
                $national = @$objectNational->childNodes[1]->childNodes[2]->nodeValue;
                $religion = @$objectNational->childNodes[3]->childNodes[2]->nodeValue;
                $data["Nationality"] = $national;
                $data["Religion"] = $religion;

                //Address
                $objectAddress = $this->getContentDiv($els, "Address");
                $address = @$objectAddress->childNodes[2]->nodeValue;
                $data['address'] = $this->removeSpace(trim($address), true);

                //Mobile
                $objectMobile = $this->getContentDiv($els, "Mobile");
                $mobile = @$objectMobile->childNodes[1]->nodeValue;
                $data["mobile"] = trim($mobile);

                //Telephone
                $objectTelephone = $this->getContentDiv($els, "Telephone ");
                $telephone = @$objectTelephone->childNodes[1]->nodeValue;
                $data["telephone "] = trim($telephone);

                //Email
                $objectEmail = $this->getContentDiv($els, "Email ");
                $email = @$objectEmail->childNodes[1]->nodeValue;
                $data["email "] = trim($email);
                return $data;
                break;
            case "job_requirement":

                //get jobCategory 1
                $categoryElement1 = $this->getContentDiv($els, "1. Job Categories");
                if(empty($categoryElement1)) {
                    $categoryElement1 = $this->getContentDiv($els, "Job Categories");
                }
                $objectCategoryEl = $this->getObjectElementJobRequirment($categoryElement1);
                $data["Job Categories 1"] = $objectCategoryEl["Value1"];
                $data['Job position 1'] = $objectCategoryEl['Value2'];
                //get jobCategory 2

                $categoryElement12 = $this->getContentDiv($els, "2. Job Categories ");
                $objectCategoryEl2 = $this->getObjectElementJobRequirment($categoryElement12);
                $data["Job Categories 2"] = $objectCategoryEl2["Value1"];
                $data['Job position 2'] = $objectCategoryEl2['Value2'];

                //get jobCategory 2
                $categoryElement13 = $this->getContentDiv($els, "3. Job Categories");
                $objectCategoryEl3 = @$this->getObjectElementJobRequirment($categoryElement13);
                $data["Job Categories 3"] = $objectCategoryEl3["Value1"];
                $data['Job position 3'] = $objectCategoryEl3['Value2'];

                //get job Location and jobtype
                $objectLocation = $this->getContentDiv($els,"Job Location");
                $jobLocation = $this->getObjectElementJobRequirment($objectLocation);
                $data["Job Location"] = $this->removeSpace(trim($jobLocation["Value1"]));
                $data["Job Type"] = trim($jobLocation["Value2"]);
                //get Expected salary
                $objectSalary = $this->getContentDiv($els, "Expected Salary");
                $expectedSalary = $this->getObjectElementJobRequirment($objectSalary);

                $data["Expected Salary"] = trim($expectedSalary["Value1"]);
                $data["Availability"] = trim($expectedSalary['Value2']);

                //get willing to work oversea
                $objectWillingGo= $this->getContentDiv($els, "Willing to work oversea?");
                $willingGo = @$objectWillingGo->childNodes[3]->nodeValue;

                $data["willing go"] = trim($willingGo);
                return $data;
                break;

            case "education":
                $objectEducation = $this->getContentDiv($els, "Education Information");
                $contentHtml = @$objectEducation->childNodes[3]->childNodes[2];
                $education  = str_replace("Education Information", " ", trim(@$objectEducation->nodeValue));
                $levelEducationHtml = $this->convertNodeToHtml($contentHtml);
                $levelEducation = explode("<br>", $levelEducationHtml);
                $levelEdu = "";
                if(isset($levelEducation[2])) {
                    $levelEducationText = explode(":", $levelEducation[2]);
                    $levelEdu = $levelEducationText[1];
                }
                $data["education"] = $this->removeSpace(trim($education));
                $data["level_education"] = $this->removeSpace(trim($levelEdu));
                return $data;
                break;
            case "workExperience":
                $objectWorkExperience = $this->getContentDiv($els, "Work Experience");
                $workExperience = @$objectWorkExperience->childNodes[3]->childNodes[1]->nodeValue;
                $data["workExperience"] = $this->removeSpace(trim($workExperience));
                return $data;
                break;
            case "skill":

                $objectSkill = $this->getContentDiv($els, "Skills & Languages");
                if(empty($objectSkill)) {
                    $xpath =  '//*[@id="left"]/div[4]/div/div';
                    $els = $this->getElement($xpath, $pageHtml);
                }else {
                    $xpath =  '//*[@id="left"]/div[5]/div/div';
                    $els = $this->getElement($xpath, $pageHtml);
                }

                //Typing Skills
                $objectTyping = $this->getContentDiv($els, "Typing Skills");
                $typing = str_replace("Typing Skills : ", " ", trim(@$objectTyping->nodeValue));
                $data["dri_typing"] = trim($typing);

                //get language
                $data["Thai"]= "";
                $data["English"]= "";
                $data["Japaneses"]= "";
                $objectLanguage = $this->getContentDiv($els, "Languages");

                //get first language
                $language1 = trim(@$objectLanguage->childNodes[3]->childNodes[1]->nodeValue);
                $valueLanguage1 = @$objectLanguage->childNodes[3]->childNodes[3]->nodeValue;
                if(strpos($language1, "Thai") !== false) {
                    $data["Thai"] = trim($valueLanguage1);
                }
                if(strpos($language1, "Japanese") !== false) {
                    $data["Japaneses"] = trim($valueLanguage1);
                }

                if(strpos($language1, "English") !== false) {
                    $data["English"] = trim($valueLanguage1);
                }

                //get second language

                $language2 = trim(@$objectLanguage->childNodes[5]->childNodes[1]->nodeValue);
                $valueLanguage2 = @$objectLanguage->childNodes[5]->childNodes[3]->nodeValue;
                if(strpos($language2, "Thai") !== false) {
                    $data["Thai"] = trim($valueLanguage2);
                }
                if(strpos($language2, "Japaneses") !== false) {echo "333";
                    $data["Japaneses"] = trim($valueLanguage2);
                }

                if(strpos($language2, "English") !== false) {
                    $data["English"] = trim($valueLanguage2);
                }
                return $data;
                break;

            case "training":
                $objectTraining = $this->getContentDiv($els, "Training Information");
                if(empty($objectTraining)) {
                    $xpath =  '//*[@id="left"]/div[5]/div/div';
                    $els = $this->getElement($xpath, $pageHtml);
                }else{
                    $xpath =  '//*[@id="left"]/div[6]/div/div';
                    $els = $this->getElement($xpath, $pageHtml);
                }
                //period
                $objectPeriod = $this->getContentDiv($els, "Period");
                $period = @$objectPeriod->childNodes[1]->childNodes[3]->nodeValue;
                $data['period'] = trim($period);

                $institute = @$objectPeriod->childNodes[1]->childNodes[8]->nodeValue;
                $data["institute"] = trim($institute);

                $course = @$objectPeriod->childNodes[1]->childNodes[13]->nodeValue;
                $data["course"] = trim($course);
                return $data;
                break;
            default:
            break;
        }

    }

    /**
     * Get the div contain the title
     * @param $els
     * @param $title
     * @return bool
     */
    private function getContentDiv($els, $title){
        foreach ($els as $el) {
            if ($el == null )
                continue;
            $elTitle = $el->nodeValue;
            if (strpos($elTitle,$title) !== false ) {
                return $el;
            }
        }
        return false;
    }

    /**
     * get cvObject
     * @param null $link
     * @return array
     */
    private function getDetailCVObject($html, $link)
    {
        $arrXPath = array(
            array("key" => "fullName", "xpath" => '//*[@id="left"]/div/div[4]/div/div/b'),
            array("key" => "profile", "xpath" => '//*[@id="left"]/div/div[4]/div/div[3]/div/div'),
            array("key" => "job_requirement", "xpath" => '//*[@id="left"]/div[2]/div/div'),
            array("key" => "education", "xpath" =>  '//*[@id="left"]/div[3]/div'),
            array("key" => "workExperience", "xpath" =>  '//*[@id="left"]/div[4]/div'),
            array("key" => "skill", "xpath" =>  '//*[@id="left"]/div[5]/div'),
            array("key" => "training", "xpath" =>  '//*[@id="left"]/div[6]/div'),
        );
        $cVObject = array();
        $cVObject['detailUrl'] = $link;
        foreach($arrXPath as &$xPath){
            $xPath['elements'] = $this->getElement($xPath['xpath'], $html);
            $data = $this->processGetContent($xPath, $html);
            $cVObject = array_merge($cVObject, $data);
        }
        $cVObject["created_at"] = Date("Y-m-d");
        $cVObject["updated_at"] = Date("Y-m-d");
       return $cVObject;

    }

    /**
     * input is array
     * create file CSV
     * @param null $object
     * @param null $fileName
     */
    private function exportCsv($object, $fileName)
    {
        $header = array("No", "detailUrl", "Title", "Name","Gender", "Date of Birth", "Age", "Marital Status","Malitary Status" ,"Nationality", "Religion",
            "Address", " Mobile", "Telephone", "Email", "Job Categories1", "Job Position1", "Job Categories2", "Job Position2", "Job Categories3", "Job Position3",
            "Job Location", "Job Type", "Expected Salary", "Availability", "Willing to word oversea?", "Education Information","Level education",
            "Work Experience", "Typing Skills", "Languages(Thai)", "Languages(Eng)", "Languages(Jap)", "Period", "Institute", "Course", "Updated Date", "Created Date");

        if(file_exists($fileName)) {
            $fp = fopen($fileName, 'a+');
            $file = escapeshellarg($fileName);
            $line = `tail -n 1 $file`;
            $no = explode(",", $line);
            $i = (int)$no[0];
            foreach ($object as $fields) {

                $i++;
                $arrNo = array($i);
                $row = array_merge($arrNo, $fields);
                fputcsv($fp, $row);
            }
            fclose($fp);
        }else {

            $fp = fopen($fileName, 'wb');
            fputcsv($fp, $header);
            $i = 0;
            foreach ($object as $fields) {
                $i++;
                $arrNo = array($i);
                $row = array_merge($arrNo, $fields);
                fputcsv($fp, $row);
            }
            fclose($fp);
            chmod($fileName, 0664);

        }



    }

    /**
     * set currentPage getting data
     * @param $currentPage
     */
    protected function setCurrentPage($currentPage)
    {
        $path = $this->_rootPath.'tmp/current_page.txt';
        $data = array('currentPage' => $currentPage);
        $this->saveToConfigFile($data, $path);
    }

    private function getCurrentPage(){
        $path = $this->_rootPath."tmp/cuurent_page.txt";
        $data = $this->getConfigFile($path);
        if (isset($data['currentPage'])) {
            return $data['currentPage'];
        }
        return 0;
    }

    private function saveToConfigFile($data, $file){
        $chmod = false;
        if (!file_exists($file)) {
            $chmod = true;
        }
        $file = fopen($file, 'w');
        foreach ($data as $key => $value)
        {
            $rowText = $key.'='.$value.PHP_EOL;
            fwrite($file, $rowText);
        }
        fclose($file);
        if ($chmod)
            chmod($file, 0664);
    }

    /**
     * get CurrentPage got data
     * @return array
     */
    protected function getConfigFile($filePath)
    {
        $content = array();
        $fileName = $filePath;
        if(file_exists($fileName)) {
            if(filesize($fileName) > 0){
                $fp = fopen($fileName, 'rb');
                $line = fgets($fp);
                while ($line !== false) {
                    if (empty($line)) continue;
                    $arr = explode("=", $line);
                    $content[trim($arr[0])] = trim(@$arr[1]);
                    $line = fgets($fp);
                }
                fclose($fp);
            }
        }
        return $content;
    }

    private function convertNodeToHtml ($element){
        if ($element==null)
            return "";
        $newdoc = new DOMDocument();
        $cloned = $element->cloneNode(TRUE);
        $newdoc->appendChild($newdoc->importNode($cloned,TRUE));
        return $newdoc->saveHTML();
    }


}
