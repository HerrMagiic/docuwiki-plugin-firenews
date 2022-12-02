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
        $this->Lexer->addSpecialPattern('\{\{firenews>[^}]*\}\}', $mode, 'plugin_firenews');
    }

    /** @inheritDoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        // gets the string after the >
        $match = explode(">", substr($match, 0, -2)); 

        // saves the string into $params
        $params = $match[1];

        // add the found string into an array with the key 'param'
        $datatest_conf              = array();
        $datatest_conf['param']     = $params;

        // I think this is useless 
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
        $tablename = $pluginname;

        // Connect to database with sqlite plugin
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
            // When param matches creates the Author template on the page
            if ($data['param'] === "author") {

                // Gets the html file that will get added to the page
                $formView = file_get_contents(__DIR__ . "/HTMLTemplates/author/author.html");
                // Applies the correct language from the lang/language
                $formView = $this->setLanguage($formView, "/\{\{firenews\_(\w+)\}\}/");

                // replaces the {{author}} tag with the current user name
                $formView = str_replace("{{author}}", "{$USERINFO['name']}", $formView);

                // if the form is submitted
                if (isset($_POST["submitted"])) {

                    /**
                     * Adds a {{firenews>}} tag to the targetpage if not exists
                     * And adds the submitted information in the database
                     */

                    // Explodes the string to get the targetpage Path
                    $pagelocation = explode(':', $_POST['ltargetpage']);
                    $pagepath = __DIR__ . "\..\..\..\data\pages";
                    foreach ($pagelocation as $value) {
                        $pagepath .= "\\" . $value;
                    }
                    $pagepath .= ".txt";

                    // If file don't exists return false and add a error message
                    if (!file_exists($pagepath)) {
                        $formView = str_replace("{{ script_placeholder }}", 
                        <<<HTML
                        <script>
                            // This will trigger the error message see HTMLTempalte/author/author.js
                            window.location = window.location.href + "&fileexists=false";
                        </script>
                        HTML, $formView);

                        // This adds the html to the page
                        $renderer->doc .= $formView;
                        return false;
                    } 

                    // NOCACHE needed so everyting gets updates correctly
                    // ToDo find a way to line break
                    $nocacheTag = "~~NOCACHE~~";
                    $insertAnchor = "{{firenews>{$_POST['ltargetpage']}}}";

                    // Writes the tag into the targetpage
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

                // Creating the edit news Page
                $editnewsTemplate = file_get_contents(__DIR__ . "/HTMLTemplates/editnews/editnewsTemplate.html");
                // Applies the correct language from the lang/language
                $editnewsTemplate = $this->setLanguage($editnewsTemplate, "/\{\{firenews\_(\w+)\}\}/");

                $editnews = file_get_contents(__DIR__ . "/HTMLTemplates/editnews/editnews.html");
                // Applies the correct language from the lang/language
                $editnews = $this->setLanguage($editnews, "/\{\{firenews\_(\w+)\}\}/");
                
                $outputRender = "";
                // if the form is submitted on save 
                if (isset($_POST['savesubmit'])) {
                    // Update database
                    $sqlite->query("UPDATE {$tablename} 
                                        SET header = '{$_POST['eheader']}',
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
                // if the from is submitted on delete
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
                    // Goes through the results and adds them to $outputRender
                    foreach ($result as $value) {
                        $outputRender .= str_replace(
                            array("{{HEADER}}", "{{SUBTITLE}}", "{{TARGETPAGE}}", "{{STARTDATE}}", "{{ENDDATE}}", "{{NEWS}}", "{{GROUP}}", "{{AUTHOR}}", "{{NEWSID}}"),
                            array("{$value['header']}", "{$value['subtitle']}", "{$value['targetpage']}", "{$value['startdate']}", "{$value['enddate']}", "{$value['news']}", "{$value['group']}", "{$value['author']}", "{$value['news_id']}"),
                            $editnews
                        );
                    }
                }
                // Replaces the news tag with the outputRender
                $formView = str_replace("{{NEWS}}", $outputRender, $editnewsTemplate);

                // Appens the html to the page
                $renderer->doc .= $formView;
                return true;
            }

            /**
             * This part adds the news to the right page
             */

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
                $newsTemplate = file_get_contents(__DIR__ . "/HTMLTemplates/news/news.html");

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
                // Puts the html to the page
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
     * @param string $groups Groups from $_POST['lgroup']
     * 
     * @return [array] array of emails
     */
    private function getUsersEmailsOfaGroup(string $groups): array
    {
        // Explodes the group string
        $groupArr = explode(",", $groups);
        // Gets the user.auth.php where all groups are in (maybe there is a better way)
        $filestream = fopen(__DIR__ . "\..\..\..\conf\users.auth.php", 'r');
        $listOfEmails = [];
        
        // Goes through the file
        while (feof($filestream)) {

            $currentLine = fgets($filestream);
            // We want to ignore comments
            if (str_starts_with($currentLine, "# ")) {
                continue;
            }
            // We want to ignore empty lines
            if (strlen($currentLine) < 20) {
                continue;
            }
            // Maybe here are more possible errors that could happen

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
     * @param string $groups
     * 
     * @return [bool]
     */
    private function isInGroup(string $groups): bool {
        global $INFO;
        $groupArr = explode(",", $groups);

        // Ignores everything if the user is a admin or manager
        if($INFO['isadmin'] || $INFO['ismanager'] ) { return true; }
        
        foreach($groupArr as $value) {
            if(in_array($value, $INFO['userinfo']['grps'])) { return true; }
        }

        return false;
    }

    
    /**
     * This function will search through the given file
     * and replaces found language tags with the language in the file
     * 
     * @param string $file string that should get the replacements
     * @param string $pattern regex pattern to search for
     * 
     * @return string with the right language
     */
    private function setLanguage(string $file, string $pattern): string {
        
        $result = preg_replace_callback($pattern, 
                function($matches) { 
                    $langTag = str_replace(["{{", "}}"], "", $matches[0]);
                    $lang = $this->getLang($langTag);
                    return $lang;
                },
                $file );
        return $result;
    }
}
