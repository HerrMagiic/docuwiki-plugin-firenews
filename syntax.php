<?php

/**
 * DokuWiki Plugin firenews (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  NilsSchucka <nils@schucka.de>
 */
class syntax_plugin_firenews extends \dokuwiki\Extension\SyntaxPlugin
{

    /** @inheritDoc */
    public function getType(){ return 'substition'; }

    /** @inheritDoc */
    public function getSort() { return 32; }

    /** @inheritDoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('\{\{ninews>[^}]*\}\}', $mode, 'plugin_firenews');
    }

    /** @inheritDoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // gets the string after the >
        $match = explode(">", substr($match, 0, -2)); 

        // saves the string into $params
        $params = $match[1];

        // add the found string into an array with the key param
        $datatest_conf              = array();
        $datatest_conf['param']     = $params;

        if (!$params) {
            msg('Syntax detected but unknown parameter was attached.', -1);
        } else {
            // returns the array. This array can be used in the render() function as $data
            return $datatest_conf;
        }
    }

    /** @inheritDoc */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        // Variables
        global $USERINFO, $conf;
        $pluginname = "firenews";
        $tablename = "firenews";

        //connect to database with sqlite plugin
        $sqlite = $this->sqlConnection($pluginname);

        // Create table if not exists
        $sqlite->query("CREATE TABLE IF NOT EXISTS $tablename 
                (
                    'news_id' INTEGER PRIMARY KEY AUTOINCREMENT,
                    'header' TEXT NOT NULL,
                    'subtitle' TEXT NOT NULL,
                    'targetpage' TEXT NOT NULL,
                    'startdate' DATE NOT NULL,
                    'enddate' DATE NOT NULL,
                    'news' TEXT NOT NULL,
                    'group' TEXT NOT NULL,
                    'author' TEXT
                )");


        // Checks if mode is xhtml
        if ($mode === 'xhtml') {
            // When param matches creates the template on the page
            if ($data['param'] === "author") {

                // gets the html file that will get added to the page
                $formView = file_get_contents(__DIR__ . "/templates/author/author.html");

                // replaces the {{author}} tag with the current user name
                $formView = str_replace("{{author}}", "{$USERINFO['name']}", $formView);

                // if the form is submitted
                if (isset($_POST["submitted"])) {

                    // Adds a {{ninews>}} tag to the targetpage if not exists
                    $pagelocation = explode(':', $_POST['ltargetpage']);
                    $pagepath = __DIR__ . "\..\..\..\data\pages";
                    foreach ($pagelocation as $value) {
                        $pagepath .= "\\" . $value;
                    }
                    $pagepath .= ".txt";
                    // If file not exists return false and add a error message
                    if (!file_exists($pagepath)) {
                        $formView = str_replace("{{ script_placeholder }}", 
                        <<<HTML
                        <script>
                            // This will trigger the error message see author.js
                            window.location = window.location.href + "&fileexists=false";
                        </script>
                        HTML, $formView);

                        $renderer->doc .= $formView;
                        return false;
                    } 

                    // NOCACHE needed so everyting gets updates correctly
                    // ToDo find a way to line break
                    $nocacheTag = "~~NOCACHE~~";
                    $insertAnchor = "{{ninews>{$_POST['ltargetpage']}}}";

                    $fileStream = fopen($pagepath, 'a');
                    if (!strpos(file_get_contents($pagepath), $insertAnchor)) {
                        fwrite($fileStream, $nocacheTag.$insertAnchor);
                    }

                    // Send form to database
                    $sqlite->query("INSERT INTO $tablename ('header', 'subtitle', 'targetpage', 'startdate', 'enddate', 'news', 'group', 'author') 
                        VALUES ('{$_POST['fheader']}', '{$_POST['lsubtitle']}', '{$_POST['ltargetpage']}', '{$_POST['lstartdate']}', '{$_POST['lenddate']}', '{$_POST['lnews']}', '{$_POST['lgroup']}', '{$_POST['lauthor']}')");

                    /**
                     * Send emails to group members if selected
                     * You also need to setup the smtp plugin
                     */
                    if ($_POST['lsendEmails']) {
                        //Find emails of the users that are in the groups given by the POST
                        $emails = $this->getUsersEmailsOfaGroup($_POST['lgroup']);

                        $this->sendMailToUsers($emails);
                    }

                    // Form has been submitted -> reset request from POST back to GET
                    $formView = str_replace("{{ script_placeholder }}", 
                    <<<HTML
                    <script>
                        // This will trigger a info message for the user see author.js
                        window.location = window.location.href + "&submitted=true";
                    </script>
                    HTML, $formView);

                }

                // In case the form hasnt been sent
                $formView = str_replace("{{ script_placeholder }}", "", $formView);
                
                // this will add the html to the page
                $renderer->doc .= $formView;

                return true;
            } else if ($data['param'] === "editnews") {

                $editnewsTemplate = file_get_contents(__DIR__ . "/templates/editnews/editnewsTemplate.html");
                $editnews = file_get_contents(__DIR__ . "/templates/editnews/editnews.html");
                $outputRender = "";
                // if the form is submitted
                if (isset($_POST['savesubmit'])) {

                    $sqlite->query("UPDATE {$tablename} 
                                        SET 
                                            header = '{$_POST['eheader']}',
                                            subtitle = '{$_POST['esubtitle']}',
                                            targetpage = '{$_POST['etargetpage']}',
                                            startdate = '{$_POST['estartdate']}',
                                            enddate = '{$_POST['eenddate']}',
                                            'news' = '{$_POST['enews']}',
                                            'group' = '{$_POST['egroup']}',
                                            author = '{$_POST['eauthor']}'
                                        WHERE news_id = {$_POST['enewsid']}"
                                    );
                    // Resets the POST request to GET
                    $editnewsTemplate = str_replace("{{ script_placeholder }}", 
                    <<<HTML
                    <script>
                        // This will trigger a info message for the user see author.js
                        window.location = window.location.href + "&submitted=saved";
                    </script>
                    HTML, $editnewsTemplate);
                }
                if (isset($_POST["deletesubmit"])) {
                    $sqlite->query("DELETE FROM {$tablename}
                                        WHERE news_id = {$_POST['enewsid']}"
                                    );
                    // Resets the POST request to GET
                    $editnewsTemplate = str_replace("{{ script_placeholder }}", 
                    <<<HTML
                    <script>
                        // This will trigger a info message for the user see author.js
                        window.location = window.location.href + "&submitted=deleted";
                    </script>
                    HTML, $editnewsTemplate);
                    
                }
                
                // Gets the news with the right page
                $result = $sqlite->query("SELECT * FROM {$tablename} ORDER BY news_id DESC");

                if ($result != NULL || $result != false) {
                    foreach ($result as $value) {
                        $outputRender .= str_replace(
                            array("{{HEADER}}", "{{SUBTITLE}}", "{{TARGETPAGE}}", "{{STARTDATE}}", "{{ENDDATE}}", "{{NEWS}}", "{{GROUP}}", "{{AUTHOR}}", "{{NEWSID}}"),
                            array("{$value['header']}", "{$value['subtitle']}", "{$value['targetpage']}", "{$value['startdate']}", "{$value['enddate']}", "{$value['news']}", "{$value['group']}", "{$value['author']}", "{$value['news_id']}"),
                            $editnews
                        );
                    }
                }
                
                $formView = str_replace("{{NEWS}}", $outputRender, $editnewsTemplate);
                $renderer->doc .= $formView;
                return true;

            }
            // Gets the news with the right page
            $result = $sqlite->query("SELECT * FROM {$tablename} 
                                        WHERE 
                                            targetpage = '{$data['param']}' AND
                                            startdate <= strftime('%Y-%m-%d','now') AND
                                            enddate >= strftime('%Y-%m-%d','now')
                                            ORDER BY news_id DESC
                                            LIMIT 5
                                    ");

            // If the page is found create the news
            if ($result != NULL || $result != false) {
                // Gets the news template
                $newsTemplate = file_get_contents(__DIR__ . "/templates/news/news.html");
                $outputRender = "";
                // adds news to the page that was returned by the database
                foreach ($result as $value) {

                    // Check if group is set
                    if(strlen($value['group']) > 0) {

                        //Check if only a group can see the message
                        if ($this->isInGroup($value['group']) === false) { continue; }

                        // Replaces the placeholders with the right values
                        $outputRender .= str_replace(
                            array("{{HEADER}}", "{{SUBTITLE}}", "{{DATE-AUTHOR}}", "{{NEWS}}"),
                            array("{$value['header']}", "{$value['subtitle']}", "{$value['startdate']}, {$value['author']}", "{$value['news']}"),
                            $newsTemplate
                        );
                        
                        continue;
                    }

                    // Replaces the placeholders with the right values
                    $outputRender .= str_replace(
                        array("{{HEADER}}", "{{SUBTITLE}}", "{{DATE-AUTHOR}}", "{{NEWS}}"),
                        array("{$value['header']}", "{$value['subtitle']}", "{$value['startdate']}, {$value['author']}", "{$value['news']}"),
                        $newsTemplate
                    );
                }
                $renderer->doc .= $outputRender;
                
                return true;
            }
        }
        return false;
    }


    /**
     * Gets the sqlite connection
     * 
     * @param string $pluginname name of the current plugin
     * 
     * @return [type]
     */
    private function sqlConnection(string $pluginname)
    {
        /** @var helper_plugin_sqlite $sqlite */
        $sqlite = plugin_load('helper', 'sqlite');
        if (!$sqlite) {
            msg('This plugin requires the sqlite plugin. Please install it', -1);
            return;
        }
        // initialize the database connection
        $dbname = $pluginname;
        $updatedir = DOKU_PLUGIN . "$pluginname/db/";

        if (!$sqlite->init($dbname, $updatedir)) {
            die('error init db');
        }

        return $sqlite;
    }

    /**
     * Gets user from groups
     * @param mixed $groups Groups from $_POST['lgroup']
     * 
     * @return [array] array of emails
     */
    private function getUsersEmailsOfaGroup($groups)
    {
        $groupArr = explode(",", $groups);
        $filestream = fopen(__DIR__ . "\..\..\..\conf\users.auth.php", 'r');
        $listOfEmails = [];

        while (feof($filestream)) {

            $currentLine = fgets($filestream);
            if (str_starts_with($currentLine, "# ")) {
                continue;
            }
            if (strlen($currentLine) < 20) {
                continue;
            }
            $explodeFile = explode(":", $currentLine);
            $emailOfUser = $explodeFile[3];
            $groupsOfUser = array_slice($explodeFile, 3);

            foreach ($groupsOfUser as $group) {
                foreach ($groupArr as $neededGroup) {
                    if ($group === $neededGroup) {
                        array_push($listOfEmails, $emailOfUser);
                    }
                }
            }
        }
        return $listOfEmails;
    }

    /**
     * Sends an email to the users
     * @param array $emails array of emails
     * 
     * @return [void]
     */
    private function sendMailToUsers(array $emails) {
        /** @var helper_plugin_smtp $sqlite */
        $mail = new Mailer();
        $mail->setHeader("nice Header","value", true);
        $mail->setBody("nice body");
        $mail->to($emails);

        $mail->send();
    }
    
    /**
     * Checks if the user is in one of those groups
     * @param mixed $groups
     * 
     * @return [bool]
     */
    private function isInGroup($groups) {
        global $INFO;
        $groupArr = explode(",", $groups);

        // Ignores everything if the user is a admin or manager
        if($INFO['isadmin'] || $INFO['ismanager'] ) { return true; }
        
        foreach($groupArr as $value) {
            if(in_array($value, $INFO['userinfo']['grps'])) { return true; }
        }

        return false;
    }
}
