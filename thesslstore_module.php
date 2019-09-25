<?php

/**
 * TheSslStore Module
 *
 */
class ThesslstoreModule extends Module {

    /**
     * @var string The version of this module
     */
    private static $version = "1.9.0";

    /**
     * @var string The name of this module
     */
    private static $name = "TheSSLStore Module";

    /**
     * @var string API Partner Code
     */

    private $api_partner_code = '';

    /**
     * @var string API Mode
     */

    private $is_sandbox_mode = 'n';
    /**
     * @var string The authors of this module
     */
    private static $authors = array(
        array('name' => "The SSL Store", 'url' => "https://www.thesslstore.com")
    );

    /**
     * Initializes the module
     */
    public function __construct() {
        // Load components required by this module
        Loader::loadComponents($this, array("Input"));

        // Load the language required by this module
        Language::loadLang("thesslstore_module", null, dirname(__FILE__) . DS . "language" . DS);
    }

    /**
     * Returns the name of this module
     *
     * @return string The common name of this module
     */
    public function getName() {
        return self::$name;
    }

    /**
     * Returns the version of this module
     *
     * @return string The current version of this module
     */
    public function getVersion() {
        return self::$version;
    }

    /**
     * Returns the name and URL for the authors of this module
     *
     * @return array A numerically indexed array that contains an array with key/value pairs for 'name' and 'url', representing the name and URL of the authors of this module
     */
    public function getAuthors() {
        return self::$authors;
    }

    /**
     * Performs any necessary bootstraping actions
     */
    public function install()
    {
        //Create database table
        $this->createTables();
        // Add cron tasks for this module
        $this->addCronTasks($this->getCronTasks());
    }

    /**
     * Performs any necessary cleanup actions
     *
     * @param int $module_id The ID of the module being uninstalled
     * @param boolean $last_instance True if $module_id is the last instance across
     *  all companies for this module, false otherwise
     */
    public function uninstall($module_id, $last_instance)
    {
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }
        Loader::loadModels($this, ['CronTasks']);

        $cron_tasks = $this->getCronTasks();

        if ($last_instance) {
            // Remove the cron tasks
            foreach ($cron_tasks as $task) {
                $cron_task = $this->CronTasks->getByKey($task['key'], $task['dir'], $task['task_type']);
                if ($cron_task) {
                    $this->CronTasks->deleteTask($cron_task->id, $task['task_type'], $task['dir']);
                }
            }
        }

        // Remove individual cron task runs
        foreach ($cron_tasks as $task) {
            $cron_task_run = $this->CronTasks
                ->getTaskRunByKey($task['key'], $task['dir'], false, $task['task_type']);
            if ($cron_task_run) {
                $this->CronTasks->deleteTaskRun($cron_task_run->task_run_id);
            }
        }
    }

    /**
     * Performs migration of data from $current_version (the current installed version)
     * to the given file set version. Sets Input errors on failure, preventing
     * the module from being upgraded.
     *
     * @param string $current_version The current installed version of this module
     */
    public function upgrade($current_version)
    {
        // Upgrade if possible
        if (version_compare($current_version, '1.7.0', '<')) {
            $this->addCronTasks($this->getCronTasks());
        }

        // Upgrade if possible
        if (version_compare($current_version, '1.8.0', '<')) {
            $this->createTables();
            $this->make_db_entry_in_ssl_orders();
        }

        // Upgrade if possible
        if (version_compare($current_version, '1.9.0', '<')) {
            $this->createTables();
        }
    }

    /*
     * Perform a database operation like create table etc. when module being installed
     */
    private function createTables(){
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }
        try{
            $this->Record->query("
             CREATE TABLE IF NOT EXISTS sslstore_orders(
            id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id int(10) UNSIGNED NOT NULL,
            package_id int(10) UNSIGNED NOT NULL,
            invoice_id int(10) UNSIGNED NOT NULL,
            store_order_id int(10) UNSIGNED NOT NULL,
            renew_to int(10) UNSIGNED NOT NULL DEFAULT '0',
            renew_from int(10) UNSIGNED NOT NULL DEFAULT '0',
            is_sandbox_order enum('y','n') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'n',
            created datetime DEFAULT NULL,
            PRIMARY KEY (id)
            )");

            $this->Record->query("
             CREATE TABLE IF NOT EXISTS sslstore_organisations(
            id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id int(10) UNSIGNED NOT NULL,
            org_id int(10) UNSIGNED NOT NULL,
            vendor_org_id int(10) UNSIGNED NOT NULL,
            org_name varchar(255) NOT NULL,
            is_sandbox enum('y','n') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'n',
            created datetime DEFAULT NULL,
            PRIMARY KEY (id)
            )");

            $this->Record->query("
             CREATE TABLE IF NOT EXISTS sslstore_import_orders(
            id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id int(10) UNSIGNED NOT NULL,
            service_id int(10) UNSIGNED NOT NULL,
            package_id int(10) UNSIGNED NOT NULL,
            invoice_id int(10) UNSIGNED NOT NULL,
            store_order_id int(10) UNSIGNED NOT NULL,
            package_name varchar(255) NOT NULL,
            product_code varchar(255) NOT NULL,
            term int(10) UNSIGNED NOT NULL,
            period varchar(10) NOT NULL,
            date_added datetime NOT NULL,
            date_renews datetime DEFAULT NULL,
            PRIMARY KEY (id)
            )");
        }
        catch(Exception $e){
            $this->log('Database operation', $e->getMessage(),'output', false);
        }
    }

    /*
     *  Make an entry of old records in ssl_orders table
     */

    private function make_db_entry_in_ssl_orders(){
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }
        if (!isset($this->Services)){
            Loader::loadModels($this, ['Services']);
        }

        $services = $this->getAllServiceIds();
        foreach($services as $service){

            // Fetch the service
            if (!($service_obj = $this->Services->get($service->id))) {
                continue;
            }

            $fields = $this->serviceFieldsToObject($service_obj->fields);

            // Require the SSL Store order ID field be available
            if (!isset($fields->thesslstore_order_id)){
                continue;
            }
            //the SSL Store order ID not blank
            if (empty($fields->thesslstore_order_id)){
                continue;
            }
            try{
                $inserted_order = $this->Record->select(['id'])->from("sslstore_orders")->where("store_order_id", "=", $fields->thesslstore_order_id)->fetch();
                if(!$inserted_order){
                    $invoice_data = $this->Record->select(['invoice_id'])->from("service_invoices")->where("service_id", "=", $service_obj->id)->order(array("invoice_id" => "desc"))->fetch();
                    $this->Record->insert("sslstore_orders",
                        array('service_id' => $service_obj->id,
                            'package_id' => $service_obj->package->id,
                            'invoice_id' => $invoice_data->invoice_id,
                            'store_order_id' => $fields->thesslstore_order_id,
                            'created' => $service_obj->date_added
                        )
                    );
                }
            }
            catch(Exception $e){
                $this->log('Database operation: Make Entry in ssl_orders', $e->getMessage(),'output', false);
            }
        }
    }

    /**
     * Runs the cron task identified by the key used to create the cron task
     *
     * @param string $key The key used to create the cron task
     * @see CronTasks::add()
     */
    public function cron($key)
    {
        if ($key == 'tss_order_sync') {
            $this->orderSynchronization();
        }
    }

    /**
     * Retrieves cron tasks available to this module along with their default values
     *
     * @return array A list of cron tasks
     */
    private function getCronTasks()
    {
        return [
            [
                'key' => 'tss_order_sync',
                'task_type' => 'module',
                'dir' => 'thesslstore_module',
                'name' => Language::_('ThesslstoreModule.getCronTasks.tss_order_sync_name', true),
                'description' => Language::_('ThesslstoreModule.getCronTasks.tss_order_sync_desc', true),
                'type' => 'time',
                'type_value' => '00:00:00',
                'enabled' => 1
            ]
        ];
    }

    /**
     * Attempts to add new cron tasks for this module
     *
     * @param array $tasks A list of cron tasks to add
     */
    private function addCronTasks(array $tasks)
    {
        Loader::loadModels($this, ['CronTasks']);
        foreach ($tasks as $task) {
            $task_id = $this->CronTasks->add($task);

            if (!$task_id) {
                $cron_task = $this->CronTasks->getByKey($task['key'], $task['dir'], $task['task_type']);
                if ($cron_task) {
                    $task_id = $cron_task->id;
                }
            }

            if ($task_id) {
                $task_vars = ['enabled' => $task['enabled']];
                if ($task['type'] === 'time') {
                    $task_vars['time'] = $task['type_value'];
                } else {
                    $task_vars['interval'] = $task['type_value'];
                }

                $this->CronTasks->addTaskRun($task_id, $task_vars);
            }
        }
    }

    /**
     * Synchronization order data
     */
    private function orderSynchronization()
    {
        Loader::loadModels($this, ['Services']);
        Loader::loadHelpers($this, ['Date']);
        $this->Date->setTimezone('UTC', 'UTC');

        // Get module row id
        $module_row_id = 0;
        $api_partner_code = '';
        $api_auth_token = '';
        $api_mode = '';

        $rows = $this->getModuleRows();
        foreach ($rows as $row) {
            if (isset($row->meta->thesslstore_reseller_name)) {
                $module_row_id = $row->id;
                $api_mode = $row->meta->api_mode;
                if ($api_mode == 'TEST') {
                    $api_partner_code = $row->meta->api_partner_code_test;
                    $api_auth_token = $row->meta->api_auth_token_test;
                } elseif ($api_mode == 'LIVE') {
                    $api_partner_code = $row->meta->api_partner_code_live;
                    $api_auth_token = $row->meta->api_auth_token_live;
                }
                break;
            }
        }

        $api = $this->getApi($api_partner_code, $api_auth_token, $api_mode);

        $two_month_before_date = strtotime('-2 Months') * 1000; // Convert into milliseconds
        $today_date = strtotime('now') * 1000; // Convert into milliseconds

        $order_query_request = new order_query_request();
        $order_query_request->StartDate = '/Date(' . $two_month_before_date . ')/';
        $order_query_request->EndDate = '/Date(' . $today_date . ')/';

        $order_query_resp = $api->order_query($order_query_request);

        // Cannot continue without an order query
        if (empty($order_query_resp) || !is_array($order_query_resp)) {
            return;
        }

        // Fetch all SSL Store module active/suspended services to sync
        $services = $this->getAllServiceIds();

        // Sync the renew date and FQDN of all SSL Store services
        foreach ($services as $service) {
            // Fetch the service
            if (!($service_obj = $this->Services->get($service->id))) {
                continue;
            }

            $fields = $this->serviceFieldsToObject($service_obj->fields);

            // Require the SSL Store order ID field be available
            if (!isset($fields->thesslstore_order_id)) {
                continue;
            }

            foreach ($order_query_resp as $order) {
                // Skip orders that don't match the service field's order ID
                if ($order->TheSSLStoreOrderID != $fields->thesslstore_order_id) {
                    continue;
                }

                // Update renewal date
                if (!empty($order->CertificateEndDateInUTC) && strtolower($order->OrderStatus->MajorStatus) == 'active') {
                    // Get the date 30 days before the certificate expires
                    $end_date = $this->Date->modify(
                        strtotime($order->CertificateEndDateInUTC),
                        '-30 days',
                        'Y-m-d H:i:s',
                        'UTC'
                    );

                    if ($end_date != $service_obj->date_renews) {
                        $vars['date_renews'] = $end_date . 'Z';
                        $this->Services->edit($service_obj->id, $vars, $bypass_module = true);
                    }
                }

                // Update domain name(fqdn)
                if (!empty($order->CommonName)) {
                    if (isset($fields->thesslstore_fqdn)) {
                        if ($fields->thesslstore_fqdn != $order->CommonName) {
                            // Update
                            $this->Services->editField($service_obj->id, [
                                'key' => 'thesslstore_fqdn',
                                'value' => $order->CommonName,
                                'encrypted' => 0
                            ]);
                        }
                    } else {
                        // Add
                        $this->Services->addField($service_obj->id, [
                            'key' => 'thesslstore_fqdn',
                            'value' => $order->CommonName,
                            'encrypted' => 0
                        ]);
                    }
                }
                break;
            }
        }
    }

    /**
     * Update order data
     */
    private function updateOrderData($order_resp, $service)
    {
        Loader::loadModels($this, ['Services']);
        Loader::loadHelpers($this, ['Date']);
        $this->Date->setTimezone('UTC', 'UTC');
        // Update renewal date
        if (!empty($order_resp->CertificateEndDateInUTC) && strtolower($order_resp->OrderStatus->MajorStatus) == 'active') {
            // Get the date 30 days before the certificate expires
            $end_date = $this->Date->modify(
                strtotime($order_resp->CertificateEndDateInUTC),
                '-30 days',
                'Y-m-d H:i:s',
                'UTC'
            );
            if ($end_date != $service->date_renews) {
                $vars['date_renews'] = $end_date . 'Z';
                $this->Services->edit($service->id, $vars, $bypass_module = true);
            }
        }

        $fields = $this->serviceFieldsToObject($service->fields);
        // Update domain name(fqdn)
        if (!empty($order_resp->CommonName)) {
            if (isset($fields->thesslstore_fqdn)) {
                if ($fields->thesslstore_fqdn != $order_resp->CommonName) {
                    // Update
                    $this->Services->editField($service->id, [
                        'key' => 'thesslstore_fqdn',
                        'value' => $order_resp->CommonName,
                        'encrypted' => 0
                    ]);
                }
            } else {
                // Add
                $this->Services->addField($service->id, [
                    'key' => 'thesslstore_fqdn',
                    'value' => $order_resp->CommonName,
                    'encrypted' => 0
                ]);
            }
        }
        return;
    }

    /**
     * Retrieves a list of all service IDs representing active/suspended SSL Store module services for this company
     *
     * @param array $filters An array of filter options including:
     *  - renew_start_date The service's renew date to search from
     *  - renew_end_date The service's renew date to search to
     * @return array A list of stdClass objects containing:
     *  - id The ID of the service
     */
    private function getAllServiceIds(array $filters = [])
    {
        Loader::loadComponents($this, ['Record']);

        $this->Record->select(['services.id'])
            ->from('services')
            ->on('service_fields.key', '=', 'thesslstore_order_id')
            ->innerJoin('service_fields', 'service_fields.service_id', '=', 'services.id', false)
            ->innerJoin('clients', 'clients.id', '=', 'services.client_id', false)
            ->innerJoin('client_groups', 'client_groups.id', '=', 'clients.client_group_id', false)
            ->where('services.status', 'in', ['active', 'suspended'])
            ->where('client_groups.company_id', '=', Configure::get('Blesta.company_id'));

        if (!empty($filters['renew_start_date'])) {
            $this->Record->where('services.date_renews', '>=', $filters['renew_start_date']);
        }

        if (!empty($filters['renew_end_date'])) {
            $this->Record->where('services.date_renews', '<=', $filters['renew_end_date']);
        }

        return $this->Record->group(['services.id'])
            ->fetchAll();
    }

    /**
     * Returns the value used to identify a particular service
     *
     * @param stdClass $service A stdClass object representing the service
     * @return string A value used to identify this service amongst other similar services
     */
    public function getServiceName($service) {
        foreach ($service->fields as $field) {
            if ($field->key == "thesslstore_fqdn")
                return $field->value;
        }
        return "New";
    }

    /**
     * Returns a noun used to refer to a module row (e.g. "Server", "VPS", "Reseller Account", etc.)
     *
     * @return string The noun used to refer to a module row
     */
    public function moduleRowName() {
        return Language::_("ThesslstoreModule.module_row", true);
    }

    /**
     * Returns a noun used to refer to a module row in plural form (e.g. "Servers", "VPSs", "Reseller Accounts", etc.)
     *
     * @return string The noun used to refer to a module row in plural form
     */
    public function moduleRowNamePlural() {
        return Language::_("ThesslstoreModule.module_row_plural", true);
    }

    /**
     * Returns a noun used to refer to a module group (e.g. "Server Group", "Cloud", etc.)
     *
     * @return string The noun used to refer to a module group
     */
    public function moduleGroupName() {
        return null;
    }

    /**
     * Returns the key used to identify the primary field from the set of module row meta fields.
     * This value can be any of the module row meta fields.
     *
     * @return string The key used to identify the primary field from the set of module row meta fields
     */
    public function moduleRowMetaKey() {
        return "thesslstore_reseller_name";
    }

    /**
     * Returns the value used to identify a particular package service which has
     * not yet been made into a service. This may be used to uniquely identify
     * an uncreated service of the same package (i.e. in an order form checkout)
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @return string The value used to identify this package service
     * @see Module::getServiceName()
     */
    public function getPackageServiceName($packages, array $vars=null) {
        if (isset($vars['thesslstore_reseller_name']))
            return $vars['thesslstore_reseller_name'];
        return null;
    }

    /**
     * Fetches the HTML content to display when viewing the service info in the
     * client interface.
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content containing information to display when viewing the service info
     */
    public function getClientServiceInfo($service, $package) {
        if($service->status == 'active') {
            // Load the view into this object, so helpers can be automatically added to the view
            $this->view = new View("client_service_info", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html"));

            // Get the service fields
            $service_fields = $this->serviceFieldsToObject($service->fields);

            $order_resp = $this->getSSLOrderStatus($service_fields->thesslstore_order_id, $package->meta->thesslstore_vendor_name);

            //Update Order Info
            $update_order_data_resp = $this->updateOrderData($order_resp, $service);

            if($order_resp != NULL && $order_resp->AuthResponse->isError == false) {

                $store_order_id = $order_resp->TheSSLStoreOrderID;
                $vendor_order_id = $order_resp->VendorOrderID;
                $major_status = $order_resp->OrderStatus->MajorStatus;
                $minor_status = $order_resp->OrderStatus->MinorStatus;

                $this->view->set("store_order_id", $store_order_id);
                $this->view->set("vendor_order_id", $vendor_order_id);
                $this->view->set("major_status", $major_status);
                $this->view->set("minor_status", $minor_status);


                return $this->view->fetch();
            }
        }
        return "";
    }

    /**
     * Initializes the API and returns an instance of that object with the given $partner_code, and $auth_token set
     *
     * @param string $partner_code The TheSSLStore partner Code
     * @param string $auth_token The Auth token to the TheSSLStore server
     * @param string $sandbox Whether sandbox or not
     * @param stdClass $row A stdClass object representing a single reseller
     * @return TheSSLStoreApi The TheSSLStoreApi instance
     */
    public function getApi($api_partner_code = null, $api_auth_token = null, $api_mode = 'TEST', $IsUsedForTokenSystem = false, $token= '' ) {

        Loader::load(dirname(__FILE__) . DS . "api" . DS . "thesslstoreApi.php");

        if($api_partner_code == null) {

            $module_rows = $this->getModuleRows();

            //get api data using module manager because in "manageAddRow" module data is not initiated
            if(!$module_rows) {

                $company_id = Configure::get("Blesta.company_id");
                //Load Model
                Loader::loadModels($this, array("ModuleManager"));
                $modules = $this->ModuleManager->getInstalled();

                foreach ($modules as $module) {
                    $module_data = $this->ModuleManager->get($module->id);
                    foreach ($module_data->rows as $row) {

                        if (isset($row->meta->thesslstore_reseller_name)) {

                            $api_mode = $row->meta->api_mode;
                            $module_rows = $module_data->rows;
                            $this->setModule($module);
                            $this->setModuleRow($module_rows);
                            break 2;

                        }

                    }
                }
            }


            foreach ($module_rows as $row) {
                if (isset($row->meta->api_mode)) {
                    $api_mode = $row->meta->api_mode;
                    if($api_mode == 'LIVE'){
                        $api_partner_code = $row->meta->api_partner_code_live;
                        $api_auth_token = $row->meta->api_auth_token_live;
                        $this->is_sandbox_mode = 'n';
                    }
                    else{
                        $api_partner_code = $row->meta->api_partner_code_test;
                        $api_auth_token = $row->meta->api_auth_token_test;
                        $this->is_sandbox_mode = 'y';
                    }
                    break;
                }
            }
        }

        $this->api_partner_code = $api_partner_code;

        $api = new thesslstoreApi($api_partner_code, $api_auth_token, $token, $tokenID = '', $tokenCode = '', $IsUsedForTokenSystem, $api_mode,'Blesta-'.$this->getVersion());

        return $api;
    }

    /**
     * Validates whether or not the connection details are valid by attempting to fetch
     * the number of accounts that currently reside on the server
     *
     * @param string $api_username The reseller API username
     * @param array $vars A list of other module row fields including:
     * 	- api_token The API token
     * 	- sandbox "true" or "false" as to whether sandbox is enabled
     * @return boolean True if the connection is valid, false otherwise
     */
    public function validateCredential($api_partner_code,$vars,$api_mode='TEST') {
        try {

            $api_partner_code = "";
            $api_auth_token = "";
            if($api_mode == "LIVE"){
                $api_partner_code = (isset($vars['api_partner_code_live']) ? $vars['api_partner_code_live'] : "");
                $api_auth_token = (isset($vars['api_auth_token_live']) ? $vars['api_auth_token_live'] : "");
            }
            elseif($api_mode == "TEST"){
                $api_partner_code = (isset($vars['api_partner_code_test']) ? $vars['api_partner_code_test'] : "");
                $api_auth_token = (isset($vars['api_auth_token_test']) ? $vars['api_auth_token_test'] : "");
            }

            $module_row = (object)array('meta' => (object)$vars);

            $api = $this->getApi($api_partner_code, $api_auth_token, $api_mode);

            $health_validate_request = new health_validate_request();

            $response = $api->health_validate($health_validate_request);

            if($response->isError == true)
            {
                // Log the response
                $this->log($api_partner_code, serialize($response), "output", false);
                return false;
            }
            else
            {
                // Log the response
                $this->log($api_partner_code, serialize($response), "output", true);
                return true;
            }

        }
        catch (Exception $e) {
            return false;
            // Trap any errors encountered, could not validate connection
        }
        return false;
    }

    /**
     * Retrieves a list of rules for validating adding/editing a module row
     *
     * @param array $vars A list of input vars
     * @return array A list of rules
     */
    private function getCredentialRules(array &$vars) {

        return array(
            'thesslstore_reseller_name' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_reseller_name.empty", true)
                )
            ),
            'api_partner_code_live' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.api_partner_code_live.empty", true)
                )
            ),
            'api_auth_token_live' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.api_auth_token_live.empty", true)
                ),
                'valid' => array(
                    'rule' => array(array($this, "validateCredential"), $vars, "LIVE"),
                    'message' => Language::_("ThesslstoreModule.!error.api_partner_code_live.valid", true)
                )
            ),
            'api_partner_code_test' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.api_partner_code_test.empty", true)
                )
            ),
            'api_auth_token_test' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.api_auth_token_test.empty", true)
                ),
                'valid' => array(
                    'rule' => array(array($this, "validateCredential"), $vars, "TEST"),
                    'message' => Language::_("ThesslstoreModule.!error.api_partner_code_test.valid", true)
                )
            ),
            'api_mode' => array(
            )

        );
    }

    /**
     * Retrieves a list of rules for validating adding/editing a profit margin row
     *
     * @param array $vars A list of input vars
     * @return array A list of rules
     */
    private function getImportPackageRules(array &$vars) {
        // If the option is set to percentage then only validate that value
        if($vars['import_package_mode'] == 'with_percentage') {
            return array(
                'profit_margin' => array(
                    'empty' => array(
                        'rule' => "isEmpty",
                        'negate' => true,
                        'message' => Language::_("ThesslstoreModule.!error.profit_margin.empty", true)
                    ),
                    'valid' => array(
                        'rule' => array("isPassword", 1, "num"),
                        'message' => Language::_("ThesslstoreModule.!error.profit_margin.valid", true)
                    )
                )

            );
        }
    }

    /**
     * Validates input data when attempting to add a package, returns the meta
     * data to save when adding a package. Performs any action required to add
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being added.
     *
     * @param array An array of key/value pairs used to add the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     * 	- key The key for this meta field
     * 	- value The value for this key
     * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addPackage(array $vars=null) {
        $this->Input->setRules($this->getPackageRules($vars));

        $meta = array();
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = array(
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                );
            }
        }
        return $meta;
    }

    /**
     * Validates input data when attempting to edit a package, returns the meta
     * data to save when editing a package. Performs any action required to edit
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being edited.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array An array of key/value pairs used to edit the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     * 	- key The key for this meta field
     * 	- value The value for this key
     * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function editPackage($package, array $vars=null) {
        $this->Input->setRules($this->getPackageRules($vars));

        $meta = array();
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = array(
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                );
            }
        }

        return $meta;
    }

    /**
     * Retrieves a list of rules for validating adding/editing a package
     *
     * @param array $vars A list of input vars
     * @return array A list of rules
     */
    private function getPackageRules(array $vars = null) {
        $rules = array(
            'meta[thesslstore_product_code]' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("TheSSLStore.!error.meta[thesslstore_product_code].valid", true)
                )
            ),
            'meta[thesslstore_vendor_name]' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("TheSSLStore.!error.meta[thesslstore_vendor_name].valid", true)
                )
            ),
            'meta[thesslstore_is_code_signing]' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("TheSSLStore.!error.meta[thesslstore_is_code_signing].valid", true)
                )
            ),
            'meta[thesslstore_min_san]' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("TheSSLStore.!error.meta[thesslstore_min_san].valid", true)
                )
            ),
            'meta[thesslstore_is_scan_product]' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("TheSSLStore.!error.meta[thesslstore_is_scan_product].valid", true)
                )
            ),
            'meta[thesslstore_validation_type]' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("TheSSLStore.!error.meta[thesslstore_validation_type].valid", true)
                )
            )


        );
        return $rules;
    }

    /**
     * Returns the rendered view of the manage module page
     *
     * @param mixed $module A stdClass object representing the module and its rows
     * @param array $vars An array of post data submitted to or on the manage module page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the manager module page
     */
    public function manageModule($module, array &$vars) {

        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View("manage", "default");
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, array("Form", "Html", "Widget"));

        $link_buttons = array();
        $credential_added = false;

        foreach($module->rows as $row){
            if(isset($row->meta->thesslstore_reseller_name)){

                $credential_added = true;
                //$link_buttons[] = array('name'=>Language::_("ThesslstoreModule.replacement_order_row",true),'attributes'=>array('href'=>$this->base_uri . "settings/company/modules/addrow/" . $module->id."?scr=replacementorder"));
                $link_buttons[] = array('name'=>Language::_("ThesslstoreModule.edit_credential_row", true), 'attributes'=>array('href'=>$this->base_uri . "settings/company/modules/addrow/" . $module->id."?scr=editcredential"));
                $link_buttons[] = array('name'=>Language::_("ThesslstoreModule.edit_additional_settings_row", true), 'attributes'=>array('href'=>$this->base_uri . "settings/company/modules/addrow/" . $module->id."?scr=additionalsettings"));
                $link_buttons[] = array('name'=>Language::_("ThesslstoreModule.import_order_row",true),'attributes'=>array('href'=>$this->base_uri . "settings/company/modules/addrow/" . $module->id."?scr=importorder"));
                $link_buttons[] = array('name'=>Language::_("ThesslstoreModule.import_product_row",true),'attributes'=>array('href'=>$this->base_uri . "settings/company/modules/addrow/" . $module->id."?scr=importpackage"));
                $link_buttons[] = array('name'=>Language::_("ThesslstoreModule.setup_price_row",true),'attributes'=>array('href'=>$this->base_uri . "settings/company/modules/addrow/" . $module->id."?scr=setupprice"));
                break;
            }
        }
        if($credential_added == false){
            $link_buttons = array(
                array('name'=>Language::_("ThesslstoreModule.add_credential_row", true), 'attributes'=>array('href'=>$this->base_uri . "settings/company/modules/addrow/" . $module->id."?scr=addcredential"))
            );
        }

        $this->view->set("module", $module);
        $this->view->set("link_buttons",$link_buttons);



        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the add module row page
     *
     * @param array $vars An array of post data submitted to or on the add module row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the add module row page
     */
    public function manageAddRow(array &$vars) {
        // Load the view into this object, so helpers can be automatically added to the view
        $scr = isset($_GET['scr']) ? $_GET['scr'] : '';
        if($scr == 'addcredential') {

            $this->view = new View("add_credential", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));

            $this->view->set("vars", (object)$vars);
            return $this->view->fetch();
        }
        elseif($scr == "editcredential"){

            //This function is just called to set row meta because in current method row meta is not set by blesta.
            $this->getApi();

            $this->view = new View("add_credential", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));

            $module_rows = $this->getModuleRows();
            $vars = array();
            foreach($module_rows as $row){
                if(isset($row->meta->thesslstore_reseller_name)){
                    $vars = $row->meta;
                    $vars->module_row_id = $row->id;
                    break;
                }
            }
            $this->view->set("vars",$vars);
            return $this->view->fetch();
        }
        elseif($scr == 'importpackage'){
            $this->view = new View("import_packages", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));

            /* Retrieve the company ID */
            $companyID=Configure::get("Blesta.company_id");
            // Load the Loader to fetch Package Groups to assign to the packages
            Loader::loadModels($this, array("PackageGroups"));
            $packageGroupsArray=$this->PackageGroups->getAll($companyID);
            foreach ($packageGroupsArray as $key => $value) {
                $packageGroups[$value->id] = $value->name;
            }
            if (!empty($packageGroupsArray)) {
                $vars['packageGroups']=$packageGroups;
                $vars['packageGroupsArray'] = "true";
            }
            else
            {
                $vars['packageGroupsArray'] = "false";
            }

            // Load the Loader to fetch supported Currencies
            Loader::loadModels($this, array("Currencies"));
            $currenciesArray=$this->Currencies->getAll($companyID);
            foreach ($currenciesArray as $key => $value) {
                $currencies[$value->code] = $value->code;
                if($this->Currencies->validateCurrencyIsDefault($value->code,$companyID))
                {
                    $defaultCurrency = $value->code;
                }
            }

            if (!empty($currenciesArray)) {
                // Load the Loader to fetch Existing products from the DB
                Loader::loadModels($this, array("Packages"));
                $existingProducts = $this->Packages->getAll($companyID, $order = ['name' => 'ASC'], $status = null, $type = null );
                $already_added_packages = array();

                /* Call the function to get the module name */
                $moduleName = $this->getName();

                /* Load the Loader to fetch info of All the installed Module */
                Loader::loadModels($this, array("ModuleManager"));
                $moduleArray = $this->ModuleManager->getInstalled();
                /* Retrieve the Company ID which was assigned to our module */
                $moduleIDObject = null;
                foreach ($moduleArray as $info) {
                    if ($moduleName == $info->name) {
                        $moduleIDObject = $info;
                        break;
                    }
                }
                $moduleID = $moduleIDObject->id;

                foreach($existingProducts as $pack_data){
                    if ($pack_data->module_id == $moduleID) {
                        $package = $this->Packages->get($pack_data->id);
                        $already_added_packages[$pack_data->id] = $package->meta->thesslstore_product_code;
                    }
                }

                //Get products
                $products = $this->getProducts();
                //echo "<pre>";
                //print_r($products);die();
                //TSS API supports USD,EUR currency
                $apiCurrencyCode = (isset($products[0]->CurrencyCode) ? $products[0]->CurrencyCode : '') ;
                $currencyDetails = $this->Currencies-> get($apiCurrencyCode,$companyID);
                $api_currency_rate = $currencyDetails->exchange_rate;

                $vars['currencies'] = $currencies;
                $vars['currenciesArray'] = "true";
                $vars['defaultCurrency'] = $defaultCurrency;
                $vars['apiCurrencyCode'] = $apiCurrencyCode;
                $vars['products'] = $products;
                $vars['existingProducts'] = $already_added_packages;
                $vars['api_currency_rate'] = $api_currency_rate;
                $vars['product_edit_url'] = "/admin/packages/edit/";
            }
            else
            {
                $vars['currenciesArray'] = "false";
            }
            // Set unspecified checkboxes

            $this->view->set("vars", (object)$vars);
            return $this->view->fetch();
        }
        elseif($scr == 'setupprice'){
            $this->view = new View("setup_price", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));

            //Get current company ID
            $company_id = Configure::get("Blesta.company_id");

            // Load the Loader to fetch supported Currencies
            Loader::loadModels($this, array("Currencies"));
            $currenciesArray=$this->Currencies->getAll($company_id);
            foreach ($currenciesArray as $key => $value) {
                $currencies[$value->code] = $value->code;
                if($this->Currencies->validateCurrencyIsDefault($value->code,$company_id))
                {
                    $defaultCurrency = $value->code;
                }
            }
            if(isset($_REQUEST['currency'])) {
                $defaultCurrency = $_REQUEST['currency'];
            }
            //Get products
            $products = $this->getProducts();
            $apiCurrencyCode = (isset($products[0]->CurrencyCode) ? $products[0]->CurrencyCode : '') ;
            //Check the api currency is setup there or not
            $isApiCurrencySet = $this->Currencies-> get($apiCurrencyCode,$company_id);
            if (!empty($isApiCurrencySet)) {
                $vars['isApiCurrencySet'] = "true";
            }
            else
            {
                $vars['isApiCurrencySet'] = "false";
            }

            //Load Packages Model
            Loader::loadModels($this, array("Packages","PackageOptions"));

            $package_data = array();
            $packages = $this->Packages->getAll($company_id, $order=array('id'=>"ASC"), $status='active');
            foreach($packages as $pack){

                $package = $this->Packages->get($pack->id);

                if(isset($package->meta->thesslstore_product_code) && ($package->pricing[0]->currency == $defaultCurrency || $package->pricing[1]->currency == $defaultCurrency || $package->pricing[2]->currency == $defaultCurrency)) {

                    $package_data[$pack->id]['name'] = $package->name;
                    $package_data[$pack->id]['group_name'] = isset($package->groups[0]->name) ? $package->groups[0]->name : '';
                    $package_data[$pack->id]['product_code'] =  $package->meta->thesslstore_product_code;
                    foreach($package->pricing as $pricing){
                        if((($pricing->term == 12 && $pricing->period == 'month') || ($pricing->term == 1 && $pricing->period == 'year')) && $pricing->currency == $defaultCurrency){
                            $package_data[$pack->id]['pricing']['1year']['pricing_id'] = $pricing->pricing_id;
                            $package_data[$pack->id]['pricing']['1year']['price'] = $pricing->price;
                        }
                        elseif((($pricing->term == 24 && $pricing->period == 'month') || ($pricing->term == 2 && $pricing->period == 'year')) && $pricing->currency == $defaultCurrency){
                            $package_data[$pack->id]['pricing']['2year']['pricing_id'] = $pricing->pricing_id;
                            $package_data[$pack->id]['pricing']['2year']['price'] = $pricing->price;
                        }
                        elseif((($pricing->term == 36 && $pricing->period == 'month') || ($pricing->term == 3 && $pricing->period == 'year')) && $pricing->currency == $defaultCurrency){
                            $package_data[$pack->id]['pricing']['3year']['pricing_id'] = $pricing->pricing_id;
                            $package_data[$pack->id]['pricing']['3year']['price'] = $pricing->price;
                        }
                    }
                    //Get Options Price
                    $package_data[$pack->id]['has_additional_san'] = false;
                    $package_data[$pack->id]['has_additional_server'] = false;
                    $options = $this->PackageOptions->getByPackageId($pack->id);
                    foreach($options as $option){
                        if($option->name == 'additional_san' || $option->name == 'additional_server'){
                            if($option->name == 'additional_san'){
                                $key = 'san';
                                $package_data[$pack->id]['has_additional_san'] = true;
                            }

                            if($option->name == 'additional_server'){
                                $key = 'server';
                                $package_data[$pack->id]['has_additional_server'] = true;
                            }

                            if(isset($option->values[0]->pricing)){
                                foreach($option->values[0]->pricing as $pricing){
                                    if((($pricing->term == 12 && $pricing->period == 'month') || ($pricing->term == 1 && $pricing->period == 'year')) && $pricing->currency == $defaultCurrency){
                                        $package_data[$pack->id][$key.'_pricing']['1year']['pricing_id'] = $pricing->pricing_id;
                                        $package_data[$pack->id][$key.'_pricing']['1year']['price'] = $pricing->price;
                                    }
                                    elseif((($pricing->term == 24 && $pricing->period == 'month') || ($pricing->term == 2 && $pricing->period == 'year' )) && $pricing->currency == $defaultCurrency){
                                        $package_data[$pack->id][$key.'_pricing']['2year']['pricing_id'] = $pricing->pricing_id;
                                        $package_data[$pack->id][$key.'_pricing']['2year']['price'] = $pricing->price;
                                    }
                                    elseif((($pricing->term == 36 && $pricing->period == 'month') || ($pricing->term == 3 && $pricing->period == 'year')) && $pricing->currency == $defaultCurrency){
                                        $package_data[$pack->id][$key.'_pricing']['3year']['pricing_id'] = $pricing->pricing_id;
                                        $package_data[$pack->id][$key.'_pricing']['3year']['price'] = $pricing->price;
                                    }

                                }
                            }
                        }
                    }
                }
            }
            $vars['currencies'] = $currencies;
            $vars['currenciesArray'] = "true";
            $vars['defaultCurrency'] = $defaultCurrency;
            $vars['apiCurrencyCode'] = $apiCurrencyCode;

            $request_uri = explode("?",$_SERVER['REQUEST_URI']);
            $setup_price_link = $request_uri[0]."?scr=setupprice";
            $reseller_price_link = $request_uri[0]."?scr=resellerprice";
            $update_currency_link = $request_uri[0]."?scr=updatecurrency";
            $this->view->set("package_data", $package_data);
            $this->view->set("vars",(object)$vars);
            $this->view->set("setup_price_link",$setup_price_link);
            $this->view->set("reseller_price_link",$reseller_price_link);
            $this->view->set("update_currency_link",$update_currency_link);
            return $this->view->fetch();
        }
        elseif($scr == 'resellerprice'){
            $this->view = new View("reseller_price", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Html", "Widget"));

            $products = $this->getProducts();
            $module_rows = $this->getModuleRows();
            $api_mode = '';
            foreach($module_rows as $row){
                if(isset($row->meta->api_mode)){
                    $api_mode = $row->meta->api_mode;
                    break;
                }
            }

            $reseller_pricing = array();
            foreach($products as $product){
                $reseller_pricing[$product->ProductCode]['has_additional_san'] = false;
                $reseller_pricing[$product->ProductCode]['has_additional_server'] = false;
                $has_additonal_san = false;
                $has_additonal_server = false;


                if($product->MaxSan - $product->MinSan > 0) {
                    $reseller_pricing[$product->ProductCode]['has_additional_san'] = true;
                    $has_additonal_san = true;
                }

                if($product->isNoOfServerFree == false && $product->isCodeSigning == false && $product->isScanProduct == false){
                    $reseller_pricing[$product->ProductCode]['has_additional_server'] = true;
                    $has_additonal_server = true;
                }

                $reseller_pricing[$product->ProductCode]['name'] = $product->ProductName;
                foreach($product->PricingInfo as $pricing_info){
                    if($pricing_info->NumberOfMonths == 12){
                        $reseller_pricing[$product->ProductCode]['1year_price'] = number_format($pricing_info->Price, 2, '.','');
                        if($has_additonal_san){
                            $reseller_pricing[$product->ProductCode]['1year_san_price'] = number_format($pricing_info->PricePerAdditionalSAN, 2, '.', '') ;
                        }
                        if($has_additonal_server){
                            $reseller_pricing[$product->ProductCode]['1year_server_price'] = number_format($pricing_info->PricePerAdditionalServer, 2, '.', '');
                        }
                    }
                    elseif($pricing_info->NumberOfMonths == 24){
                        $reseller_pricing[$product->ProductCode]['2year_price'] = number_format($pricing_info->Price, 2, '.', '');
                        if($has_additonal_san){
                            $reseller_pricing[$product->ProductCode]['2year_san_price'] = number_format($pricing_info->PricePerAdditionalSAN, 2, '.', '');
                        }
                        if($has_additonal_server){
                            $reseller_pricing[$product->ProductCode]['2year_server_price'] = number_format($pricing_info->PricePerAdditionalServer, 2, '.', '');
                        }
                    }
                    elseif($pricing_info->NumberOfMonths == 36){
                        $reseller_pricing[$product->ProductCode]['3year_price'] = number_format($pricing_info->Price, 2, '.', '');
                        if($has_additonal_san){
                            $reseller_pricing[$product->ProductCode]['3year_san_price'] = number_format($pricing_info->PricePerAdditionalSAN, 2, '.', '');
                        }
                        if($has_additonal_server){
                            $reseller_pricing[$product->ProductCode]['3year_server_price'] = number_format($pricing_info->PricePerAdditionalServer, 2, '.', '');
                        }
                    }

                }

            }

            $this->view->set("vars", (object)$vars);
            $this->view->set("api_mode",$api_mode);
            $this->view->set("reseller_pricing",$reseller_pricing);
            return $this->view->fetch();
        }elseif($scr == "replacementorder"){
            //This function is just called to set row meta because in current method row meta is not set by blesta.
            $this->getApi();

            $this->view = new View("replacement_orders", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));

            $api = $this->getApi();
            $orders=array();
            $orderReplacementRequest = new order_replacement_request();
            if(isset($_REQUEST['date'])) {
                $replace_by_date = $_REQUEST['date'];
                $replace_by_date = strtotime($replace_by_date); //return timestamp in second.
                $replacebydate = $replace_by_date*1000; //convert into millisecond
                $orderReplacementRequest->ReplaceByDate = "/Date($replacebydate)/";
            }
            $this->log($this->api_partner_code . "|ssl-products", serialize($orderReplacementRequest), "input", true);
            if($api->order_replacement($orderReplacementRequest)->AuthResponse->isError==false)
            {
                $orders = $api->order_replacement($orderReplacementRequest)->Orders;
            }
            $export_to_csv_link = explode("?",$_SERVER['REQUEST_URI']);
            $export_to_csv_link = $export_to_csv_link[0]."?scr=exportcsv";
            $this->view->set("vars", (object)$vars);
            $this->view->set("orders",$orders);
            $this->view->set("export_to_csv_link",$export_to_csv_link);
            return $this->view->fetch();
        }elseif($scr == "exportcsv"){

            //This function is just called to set row meta because in current method row meta is not set by blesta.
            $this->getApi();

            $api = $this->getApi();

            $orderReplacementRequest = new order_replacement_request();

            $this->log($this->api_partner_code . "|ssl-products", serialize($orderReplacementRequest), "input", true);
            $orders = $api->order_replacement($orderReplacementRequest)->Orders;

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=symantecreplacementorders.csv');

            // create a file pointer connected to the output stream
            $output = fopen('php://output', 'w');
            $delimiter = ",";
            $enclosure = '"';
            $heading = array('Date', 'TheSSLStore Order ID','Vendor ID', 'Product Name', 'Common Name','Issued Date', 'Expire Date','Status','Action', 'Replace By Date');

            // output the column headings
            fputcsv($output, $heading, $delimiter, $enclosure);


            // fetch the data
            foreach ($orders as $row) {
                $line = array();
                $line[] = $row->PurchaseDate;
                $line[] = $row->TheSSLStoreOrderID;
                $line[] = $row->VendorOrderID;
                $line[] = $row->ProductName;
                $line[] = $row->CommonName;
                $line[] = $row->CertificateStartDate;
                $line[] = $row->CertificateEndDate;
                $line[] = $row->Status;
                $line[] = $row->Action;
                $line[] = $row->ReplaceByDate;

                fputcsv($output, $line, $delimiter, $enclosure);
            }

            fclose($output);
            exit;
        }elseif($scr == "additionalsettings"){

            //This function is just called to set row meta because in current method row meta is not set by blesta.
            $this->getApi();

            $this->view = new View("additional_settings", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));

            $vars = array();
            /* Call the function to get the module name */
            $moduleName = $this->getName();

            /* Load the Loader to fetch info of All the installed Module */
            Loader::loadModels($this, array("ModuleManager"));
            $moduleArray = $this->ModuleManager->getInstalled();
            /* Retrieve the Company ID which was assigned to our module */
            $moduleIDObject = null;
            foreach ($moduleArray as $info) {
                if ($moduleName == $info->name) {
                    $moduleIDObject = $info;
                    break;
                }
            }
            $moduleID = $moduleIDObject->id;
            $vars = $this->ModuleManager->getMeta($moduleID);

            $this->view->set("thesslstore_countries", $this->getCountryList());
            $this->view->set("vars",$vars);
            return $this->view->fetch();
        }elseif($scr == 'importorder'){
            $this->view = new View("import_orders", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));
            if (!isset($this->Record)){
                Loader::loadComponents($this, ['Record']);
            }
            $clientsArray = array();
            $invoiceArray = array();
            $serviceArray = array();
            $importOrderData = array();
            /* Retrieve the company ID */
            $companyID=Configure::get("Blesta.company_id");
            // Load the Loader to fetch Package Groups to assign to the packages
            Loader::loadModels($this, array("Clients","Invoices","Services"));
            $clientsArray = $this->Clients->getAll($status = 'active');

            foreach ($clientsArray as $key => $value) {
                $clients[''] = Language::_("ThesslstoreModule.please_select", true);
                $clients[$value->id] = $value->first_name.' '.$value->last_name.' (#'.$value->id.')';
            }

            $invoiceArray=$this->Invoices->getAll($client_id = null, $status = 'open', $order_by = ['id' => 'DESC'], $currency  = null);

            foreach ($invoiceArray as $key => $value) {
                $invoices[$value->id] = $value->id;
            }

            $serviceArray = $this->Record->select(['id'])->from("services")->order(array("id"=>"desc"))->fetchAll();

            foreach ($serviceArray as $key => $value) {
                $services[$value->id] = $value->id;
            }

            //Get package pricing id
            $importOrderData = $this->Record->select()->from("sslstore_import_orders")->where("date_renews", ">=", date('Y-m-d H:i:s'))->fetchAll();

            if (!empty($clients)) {
                $vars['clients'] = $clients;
                $vars['invoices'] = $invoices;
                $vars['services'] = $services;
                $vars['importOrderData'] = $importOrderData;
            }
            else
            {
                $vars['clients'] = "false";
            }
            // Set unspecified checkboxes

            $this->view->set("vars", (object)$vars);
            return $this->view->fetch();
        }
        else{
            $this->view = new View("invalid_action", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));

            $this->view->set("vars", (object)$vars);
            return $this->view->fetch();
        }
    }

    /**
     * Adds the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being added.
     *
     * @param array $vars An array of module info to add
     * @return array A numerically indexed array of meta fields for the module row containing:
     * 	- key The key for this meta field
     * 	- value The value for this key
     * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function addModuleRow(array &$vars) {
        $scr = isset($_GET['scr']) ? $_GET['scr'] : '';
        if($scr == 'addcredential') {

            foreach($this->getModuleRows() as $row){
                if(isset($row->meta->api_partner_code_live)){
                    $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.credential_already_exist", true))));
                    return false;
                }
            }
            $meta_fields = array("thesslstore_reseller_name","api_partner_code_live", "api_auth_token_live", "api_partner_code_test",
                "api_auth_token_test", "api_mode");
            $encrypted_fields = array("api_partner_code_live", "api_auth_token_live", "api_partner_code_test", "api_auth_token_test");


            $this->Input->setRules($this->getCredentialRules($vars));

            // Validate module row
            if ($this->Input->validates($vars)) {
                // Build the meta data for this row
                $meta = array();
                foreach ($vars as $key => $value) {

                    if (in_array($key, $meta_fields)) {
                        $meta[] = array(
                            'key' => $key,
                            'value' => $value,
                            'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                        );
                    }
                }

                return $meta;
            }
        }
        elseif($scr == 'editcredential'){

            $old_api_mode = '';
            foreach($this->getModuleRows() as $row){
                if(isset($row->meta->thesslstore_reseller_name)){
                    $old_api_mode = $row->meta->api_mode;
                    break;
                }
            }
            $this->Input->setRules($this->getCredentialRules($vars));
            if ($this->Input->validates($vars)) {
                $meta['thesslstore_reseller_name'] = $vars['thesslstore_reseller_name'];
                $meta['api_partner_code_live'] = $vars['api_partner_code_live'];
                $meta['api_auth_token_live'] = $vars['api_auth_token_live'];
                $meta['api_partner_code_test'] = $vars['api_partner_code_test'];
                $meta['api_auth_token_test'] = $vars['api_auth_token_test'];
                $meta['api_mode'] = $vars['api_mode'];

                Loader::loadModels($this, array("ModuleManager"));
                $this->ModuleManager->editRow($vars['module_row_id'], $meta);

                if($old_api_mode == 'TEST' && $vars['api_mode'] == 'LIVE'){
                    //Redirect with success message
                    $url = explode("?",$_SERVER['REQUEST_URI']);
                    header('Location:' . $url[0].'?scr=setupprice&msg=modeupdated');
                    exit();
                }

                //Redirect with success message
                $url = explode("?",$_SERVER['REQUEST_URI']);
                header('Location:' . $url[0].'?scr=editcredential&msg=success');
                exit();


            }

        }
        elseif($scr == 'importpackage') {
            $this->Input->setRules($this->getImportPackageRules($vars));
            if ($this->Input->validates($vars)) {
                $posted_products = $vars['products'];
                $import_package_mode = $vars['import_package_mode'];
                $currency_code = $vars['currency_code'];

                /* Retrieve the module row ID */
                $module_rows = $this->getModuleRows();
                foreach ($module_rows as $row) {
                    if (isset($row->meta->api_partner_code_live)) {
                        $moduleRowID = $row->id;
                        break;
                    }
                }
                /* Get the products array */
                $products = $this->getProducts();
                //get api currency exchange rate
                $apiCurrencyCode = (isset($products[0]->CurrencyCode) ? $products[0]->CurrencyCode : '') ;
                /* Retrieve the company ID */
                $companyID=Configure::get("Blesta.company_id");
                // Load the Loader to fetch supported Currencies
                Loader::loadModels($this, array("Currencies"));
                $currencyDetails = $this->Currencies->get($apiCurrencyCode,$companyID);
                $api_currency_rate = $currencyDetails->exchange_rate;
                //Get the selected currecny exchange rate
                $getSelectedCurrency = $this->Currencies->get($currency_code,$companyID);
                $currency_rate = $getSelectedCurrency->exchange_rate;

                /* Call the function to get the module name */
                $moduleName = $this->getName();

                /* Load the Loader to fetch info of All the installed Module */
                Loader::loadModels($this, array("ModuleManager"));
                $moduleArray = $this->ModuleManager->getInstalled();
                /* Retrieve the Company ID which was assigned to our module */
                $moduleIDObject = null;
                foreach ($moduleArray as $info) {
                    if ($moduleName == $info->name) {
                        $moduleIDObject = $info;
                        break;
                    }
                }
                $moduleID = $moduleIDObject->id;


                /* Retrieve the existing packages array for the product group */
                Loader::loadModels($this, array("Packages"));
                $packagesByGroupArray = $this->Packages->getAllPackagesByGroup($vars['product_group']);


                $alreadyAdded = array();
                foreach($packagesByGroupArray as $pack_data){
                    if ($pack_data->module_id == $moduleID) {
                        $package = $this->Packages->get($pack_data->id);
                        $alreadyAdded[] = $package->meta->thesslstore_product_code;
                    }
                }

                $already_added_packages = array();
                $packageArray = array();
                /* Set the import package count default ZERO */
                $countOfImportPackages = 0;
                foreach ($posted_products as $productCode) {
                    if (in_array($productCode, $alreadyAdded)) {
                        $already_added_packages[] = $productCode;
                    }
                    else{
                        //get Email content
                        $email_content = $this->emailContent();

                        foreach ($products as $key => $value) {
                            if ($value->ProductCode == $productCode) {
                                $packageArray['name'] = $value->ProductName;
                                $packageArray['status'] = 'active';
                                $packageArray['qty_unlimited'] = 'true';
                                $ProductDescription = $value->ProductDescription;
                                $sslfeaturelink = $value->ProductSlug;
                                $viewmorelink = "<a class='mod_view_more' href='javascript&#58;void(0);' onclick=\"window.open('$sslfeaturelink','null','location=no,toolbar=no,menubar=no,scrollbars=yes,resizable=yes,addressbar=0,titlebar=no,directories=no,channelmode=no,status=no');\"> View Full Product Details</a>";
                                $packageArray['description_html'] = $ProductDescription . $viewmorelink;
                                $packageArray['description'] = '';
                                $profitmargin = $vars['profit_margin'];


                                /* Create Option group for Additional SAN / Additional Server products */
                                $packageArray['option_groups'] = array();
                                if(($value->IsSanEnable == 'true' && $value->ProductCode != 'quicksslpremiummd') || ($value->isNoOfServerFree == false && $value->isCodeSigning == false && $value->isScanProduct == false)) {

                                    /* Load the Loader to get Package Group Name for given ID */
                                    Loader::loadModels($this, array("PackageGroups"));
                                    $packageGroupArray = $this->PackageGroups->get($vars['product_group']);
                                    $packageGroupName = $packageGroupArray->name;
                                    $optionGroupValue = array('name' => $packageGroupName . '#' . $value->ProductName, 'description' => '', 'company_id' => Configure::get("Blesta.company_id"));
                                    /* Load the Loader to add option group for respective products */
                                    Loader::loadModels($this, array("PackageOptionGroups"));
                                    $optionGroupId = $this->PackageOptionGroups->add($optionGroupValue);


                                    /* Create Configuration options of Additional SAN */
                                    if ($value->IsSanEnable == 'true' && $value->ProductCode != 'quicksslpremiummd') {
                                        $TotalMaxSan = $value->MaxSan - $value->MinSan;
                                        $PackageOptions['label'] = 'Additional SAN (' . $value->ProductName . ')';
                                        $PackageOptions['name'] = 'additional_san';
                                        $PackageOptions['type'] = 'quantity';
                                        $PackageOptions['addable'] = 1;
                                        /* Call setupPrice function to calculate the price based on the desired profit margin */
                                        $sanPricingArray = array();

                                        //setup SAN price
                                        foreach($value->PricingInfo as $price_info){
                                            if($import_package_mode == 'with_price'){
                                                $san_price = $vars[$value->ProductCode.'_'.$price_info->NumberOfMonths.'_san'];
                                                $san_price = number_format($san_price,2, '.', '');
                                            }
                                            else{
                                                $san_price = $this->setupPrice($price_info->PricePerAdditionalSAN, $profitmargin);
                                                $san_price = $this->currecncy_converter($san_price, $currency_rate, $api_currency_rate);
                                            }
                                            $sanPricingArray[] = array('term' => $price_info->NumberOfMonths, 'period' => 'month', 'currency' => $currency_code, 'price' => $san_price, 'setup_fee' => '', 'cancel_fee' => '');
                                        }


                                        if ($value->MinSan != 0) {
                                            $PackageOptions['values'][0] = array('name' => 'Additional SAN (' . $value->MinSan . ' domains are included by default)', 'value' => '', 'min' => '0', 'max' => $TotalMaxSan, 'step' => 1, 'pricing' => $sanPricingArray);
                                        } else {
                                            $PackageOptions['values'][0] = array('name' => 'Additional SAN', 'value' => '', 'min' => '0', 'max' => $TotalMaxSan, 'step' => 1, 'pricing' => $sanPricingArray);
                                        }
                                        $PackageOptions['groups'][0] = $optionGroupId;
                                        $PackageOptions['company_id'] = Configure::get("Blesta.company_id");
                                        $PackageOptions['editable'] = 0;
                                        /* Load the Loader to add options for respective option group */
                                        Loader::loadModels($this, array("PackageOptions"));
                                        $additionalSanOptionId = $this->PackageOptions->add($PackageOptions);
                                    }


                                    /* Create Configuration options of Additional SERVER */
                                    if ( $value->isNoOfServerFree == false && $value->isCodeSigning == false && $value->isScanProduct == false) {
                                        $TotalMaxServer = '9';
                                        $PackageOptions['label'] = 'Additional SERVER (' . $value->ProductName . ')';
                                        $PackageOptions['name'] = 'additional_server';
                                        $PackageOptions['type'] = 'quantity';
                                        $PackageOptions['addable'] = 1;
                                        /* Call setupPrice function to calculate the price based on the desired profit margin */
                                        $serverPricingArray = array();
                                        //Setup Server Price
                                        foreach($value->PricingInfo as $price_info){
                                            if($import_package_mode == 'with_price'){
                                                $server_price = $vars[$value->ProductCode.'_'.$price_info->NumberOfMonths.'_server'];
                                                $server_price = number_format($server_price,2, '.', '');
                                            }
                                            else{
                                                $server_price = $this->setupPrice($price_info->PricePerAdditionalServer, $profitmargin);
                                                $server_price = $this->currecncy_converter($server_price, $currency_rate, $api_currency_rate);
                                            }
                                            $serverPricingArray[] = array('term' => $price_info->NumberOfMonths, 'period' => 'month', 'currency' => $currency_code, 'price' => $server_price, 'setup_fee' => '', 'cancel_fee' => '');
                                        }
                                        $PackageOptions['values'][0] = array('name' => 'Additional SERVER', 'value' => '', 'min' => '0', 'max' => $TotalMaxServer, 'step' => 1, 'pricing' => $serverPricingArray);
                                        $PackageOptions['groups'][0] = $optionGroupId;
                                        $PackageOptions['company_id'] = Configure::get("Blesta.company_id");
                                        $PackageOptions['editable'] = 0;
                                        /* Load the Loader to add options for respective option group */
                                        Loader::loadModels($this, array("PackageOptions"));
                                        $additionalServerOptionId = $this->PackageOptions->add($PackageOptions);
                                    }
                                    $packageArray['option_groups'][0] = $optionGroupId;
                                }
                                $packageArray['module_id'] = $moduleID;
                                $packageArray['module_row'] = $moduleRowID;
                                /* Set the product type */
                                $productValidationType = 'N/A';
                                if($value->isDVProduct == 'true'){
                                    $productValidationType='DV';
                                }
                                elseif($value->isOVProduct == 'true')
                                {
                                    $productValidationType='OV';
                                }
                                elseif($value->isEVProduct == 'true')
                                {
                                    $productValidationType='EV';
                                }
                                $isScanProduct='n';
                                if($value->isScanProduct == 'true')
                                {
                                    $isScanProduct='y';
                                }
                                $isCodeSigning='n';
                                if($value->isCodeSigning == 'true')
                                {
                                    $isCodeSigning='y';
                                }
                                $packageArray['meta'] = array(
                                    'thesslstore_product_code' => $value->ProductCode,
                                    'thesslstore_min_san' => $value->MinSan,
                                    'thesslstore_vendor_name' => strtoupper($value->VendorName),
                                    'thesslstore_validation_type' => $productValidationType,
                                    'thesslstore_is_scan_product' => $isScanProduct,
                                    'thesslstore_is_code_signing' => $isCodeSigning
                                );
                                /* Get the profit margin % from the vars */
                                $packageArray['pricing'] = array();

                                //Setup Price
                                foreach($value->PricingInfo as $price_info){
                                    if($import_package_mode == 'with_price'){
                                        $final_price = $vars[$value->ProductCode.'_'.$price_info->NumberOfMonths];
                                        $final_price = number_format($final_price,2, '.', '');
                                    }
                                    else{
                                        $final_price = $this->setupPrice($price_info->Price, $profitmargin);
                                        $final_price = $this->currecncy_converter($final_price, $currency_rate, $api_currency_rate);
                                    }
                                    $packageArray['pricing'][] = array('term' => $price_info->NumberOfMonths, 'period' => 'month', 'currency' => $currency_code, 'price' => $final_price, 'setup_fee' => '', 'cancel_fee' => '');
                                }

                                $packageArray['email_content'][0] = array('lang' => 'en_us','html' => $email_content);
                                $packageArray['select_group_type'] = 'existing';
                                $packageArray['groups'][0] = $vars['product_group'];
                                $packageArray['group_name'] = '';
                                $packageArray['company_id'] = Configure::get("Blesta.company_id");
                                $packageArray['taxable'] = 0;
                                $packageArray['single_term'] = 0;
                                /* Load the Loader to add options for respective option group */
                                Loader::loadModels($this, array("Packages"));
                                $packageId = $this->Packages->add($packageArray);
                                $countOfImportPackages++;
                            }
                        }
                    }
                }
                $urlRedirect = explode("&", $_SERVER['REQUEST_URI']);
                if ($countOfImportPackages == 0) {
                    header('Location:' . $urlRedirect[0] . '&error=true'); /* Redirect browser */
                } else {
                    header('Location:' . $urlRedirect[0] . '&error=false&count=' . $countOfImportPackages); /* Redirect browser */
                }
                exit();
            }
        }
        elseif($scr == 'setupprice'){
            //Load Pricing Model
            Loader::loadModels($this, array("Currencies","Packages","PackageOptions","Pricings"));
            $currency_code = $vars['currency_code'];
            if(isset($vars['thesslstore_apply_margin']) && $vars['thesslstore_apply_margin'] == 'yes'){
                $rules = array(
                    'thesslstore_margin_percentage' => array(
                        'empty' => array(
                            'rule' => "isEmpty",
                            'negate' => true,
                            'message' => Language::_("ThesslstoreModule.!error.profit_margin.empty", true)
                        ),
                        'valid' => array(
                            'rule' => array("isPassword", 1, "num"),
                            'message' => Language::_("ThesslstoreModule.!error.profit_margin.valid", true)
                        )
                    )
                );
                //Set rules to validate fields
                $this->Input->setRules($rules);
                if ($this->Input->validates($vars)) {
                    $margin_percentage = $vars['thesslstore_margin_percentage'];
                    //Get product pricing from API
                    $api_products = $this->getProducts();
                    //get api currency exchange rate
                    $apiCurrencyCode = (isset($api_products[0]->CurrencyCode) ? $api_products[0]->CurrencyCode : '') ;
                    /* Retrieve the company ID */
                    $companyID=Configure::get("Blesta.company_id");
                    // Load the Loader to fetch supported Currencies
                    $currencyDetails = $this->Currencies->get($apiCurrencyCode,$companyID);
                    $api_currency_rate = $currencyDetails->exchange_rate;
                    //Get the selected currecny exchange rate
                    $getSelectedCurrency = $this->Currencies->get($currency_code,$companyID);
                    $currency_rate = $getSelectedCurrency->exchange_rate;

                    $products = array();
                    foreach($api_products as $product){
                        $products[$product->ProductCode] = $product->PricingInfo;
                    }

                    $packages_id = isset($vars['packages_id']) ? $vars['packages_id']: array();
                    $packages_pricing = array();

                    //Get Package data
                    foreach($packages_id as $package_id){
                        //Update package Price
                        $package = $this->Packages->get($package_id);
                        $packages_pricing[$package_id]['code'] = $package->meta->thesslstore_product_code;
                        foreach($package->pricing as $pricing){
                            $packages_pricing[$package_id][$pricing->term]['price_id'] = $pricing->pricing_id;

                            if(($pricing->term == 12 && $pricing->period == 'month') || ($pricing->term == 1 && $pricing->period == 'year') && $pricing->currency == $currency_code){
                                $packages_pricing[$package_id][12]['price_id'] = $pricing->pricing_id;
                            }
                            elseif(($pricing->term == 24 && $pricing->period == 'month') || ($pricing->term == 2 && $pricing->period == 'year' ) && $pricing->currency == $currency_code){
                                $packages_pricing[$package_id][24]['price_id'] = $pricing->pricing_id;
                            }
                            elseif(($pricing->term == 36 && $pricing->period == 'month') || ($pricing->term == 3 && $pricing->period == 'year') && $pricing->currency == $currency_code){
                                $packages_pricing[$package_id][36]['price_id'] = $pricing->pricing_id;
                            }
                            else{
                                $packages_pricing[$package_id][$pricing->term]['price_id'] = $pricing->pricing_id;
                            }
                        }

                        //Get options price
                        $options = $this->PackageOptions->getByPackageId($package_id);

                        foreach($options as $option){
                            $key = '';
                            if($option->name == 'additional_san'){
                                $key = 'san_price_id';
                            }
                            elseif($option->name == 'additional_server'){
                                $key = 'server_price_id';
                            }
                            if(isset($option->values[0]->pricing)){
                                foreach($option->values[0]->pricing as $pricing){
                                    if(($pricing->term == 12 && $pricing->period == 'month') || ($pricing->term == 1 && $pricing->period == 'year') && $pricing->currency == $currency_code){
                                        $packages_pricing[$package_id][12][$key] = $pricing->pricing_id;
                                    }
                                    elseif(($pricing->term == 24 && $pricing->period == 'month') || ($pricing->term == 2 && $pricing->period == 'year' ) && $pricing->currency == $currency_code){
                                        $packages_pricing[$package_id][24][$key] = $pricing->pricing_id;
                                    }
                                    elseif(($pricing->term == 36 && $pricing->period == 'month') || ($pricing->term == 3 && $pricing->period == 'year') && $pricing->currency == $currency_code){
                                        $packages_pricing[$package_id][36][$key] = $pricing->pricing_id;
                                    }
                                    else{
                                        $packages_pricing[$package_id][$pricing->term][$key] = $pricing->pricing_id;
                                    }

                                }
                            }
                        }
                    }

                    //Update Price in Pricing table
                    foreach($packages_pricing as $package_pricing) {
                        if (isset($products[$package_pricing['code']])) {
                            foreach($products[$package_pricing['code']] as $pricing_info){
                                //update package price
                                if(isset($package_pricing[$pricing_info->NumberOfMonths]['price_id'])){
                                    //get pricing info by id
                                    $pricing_id = $package_pricing[$pricing_info->NumberOfMonths]['price_id'];
                                    $info = $this->Pricings->get($pricing_id);

                                    //Update with new price
                                    $data['term'] = $info->term;
                                    $data['period'] = $info->period;
                                    $price = $this->setupPrice($pricing_info->Price,$margin_percentage);
                                    $final_price = $this->currecncy_converter($price, $currency_rate, $api_currency_rate);
                                    $data['price'] = $final_price;
                                    $data['setup_fee'] = $info->setup_fee;
                                    $data['cancel_fee'] = $info->cancel_fee;
                                    $data['currency'] = $info->currency;
                                    $this->Pricings->edit($pricing_id, $data);
                                }
                                //update SAN price
                                if(isset($package_pricing[$pricing_info->NumberOfMonths]['san_price_id'])){
                                    //get pricing info by id
                                    $pricing_id = $package_pricing[$pricing_info->NumberOfMonths]['san_price_id'];
                                    $info = $this->Pricings->get($pricing_id);

                                    //Update with new price
                                    $data['term'] = $info->term;
                                    $data['period'] = $info->period;
                                    $price = $this->setupPrice($pricing_info->PricePerAdditionalSAN, $margin_percentage);
                                    $final_price = $this->currecncy_converter($price, $currency_rate, $api_currency_rate);
                                    $data['price'] = $final_price;
                                    $data['setup_fee'] = $info->setup_fee;
                                    $data['cancel_fee'] = $info->cancel_fee;
                                    $data['currency'] = $info->currency;
                                    $this->Pricings->edit($pricing_id, $data);
                                }

                                //update Server price
                                if(isset($package_pricing[$pricing_info->NumberOfMonths]['server_price_id'])){
                                    //get pricing info by id
                                    $pricing_id = $package_pricing[$pricing_info->NumberOfMonths]['server_price_id'];
                                    $info = $this->Pricings->get($pricing_id);

                                    //Update with new price
                                    $data['term'] = $info->term;
                                    $data['period'] = $info->period;
                                    $price = $this->setupPrice($pricing_info->PricePerAdditionalServer, $margin_percentage);
                                    $final_price = $this->currecncy_converter($price, $currency_rate, $api_currency_rate);
                                    $data['price'] = $final_price;
                                    $data['setup_fee'] = $info->setup_fee;
                                    $data['cancel_fee'] = $info->cancel_fee;
                                    $data['currency'] = $info->currency;
                                    $this->Pricings->edit($pricing_id, $data);
                                }
                            }
                        }
                    }

                    //Redirect with success message
                    $url = explode("?",$_SERVER['REQUEST_URI']);
                    header('Location:' . $url[0].'?scr=setupprice&currency='.$currency_code.'&msg=success');
                    exit();
                }
            }
            else{
                //Update value based on textboxes
                $new_price_array = isset($vars['price']) ? $vars['price']: array();

                //Update Package data
                foreach($new_price_array as $price_id => $price_value){
                    //Get package data using pricing_id
                    $pricings = $this->Pricings->get($price_id);
                    $pricings = array($pricings);
                    foreach($pricings as $pricing){
                        $pricing_id = $pricing->id;
                        if(isset($price_value)){
                            if($price_value != $pricing->price){
                                //Update with new price
                                $data['term'] = $pricing->term;
                                $data['period'] = $pricing->period;
                                $data['price'] = $price_value;
                                $data['setup_fee'] = $pricing->setup_fee;
                                $data['cancel_fee'] = $pricing->cancel_fee;
                                $data['currency'] = $pricing->currency;

                                $this->Pricings->edit($pricing_id, $data);
                            }
                        }
                    }
                }

                $url = explode("?",$_SERVER['REQUEST_URI']);
                header('Location:' . $url[0].'?scr=setupprice&currency='.$currency_code.'&msg=success');
                exit();

            }

        }elseif($scr == 'replacementorder'){
            $replace_by_date = $vars['replace_by_date'];
            $url = explode("?",$_SERVER['REQUEST_URI']);
            header('Location:' . $url[0].'?scr=replacementorder&date='.$replace_by_date);
            exit();
        }
        elseif($scr == 'updatecurrency'){
            $currency_code = $vars['currency_code'];
            $url = explode("?",$_SERVER['REQUEST_URI']);
            header('Location:' . $url[0].'?scr=setupprice&currency='.$currency_code);
            exit();
        }
        elseif($scr == 'additionalsettings'){
            $meta_fields = array("use_default_tech_details","thesslstore_tech_job_title", "thesslstore_tech_first_name", "thesslstore_tech_last_name",
                "thesslstore_tech_org_name", "thesslstore_tech_address", "thesslstore_tech_phone", "thesslstore_tech_email", "thesslstore_tech_city", "thesslstore_tech_state", "thesslstore_tech_country", "thesslstore_tech_zipcode","additional_days_for_neworder","additional_days_for_reneworder");
            $encrypted_fields = array();

            // Build the meta data
            $meta = array();
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = array(
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    );
                }
            }
            /* Call the function to get the module name */
            $moduleName = $this->getName();

            /* Load the Loader to fetch info of All the installed Module */
            Loader::loadModels($this, array("ModuleManager"));
            $moduleArray = $this->ModuleManager->getInstalled();
            /* Retrieve the Company ID which was assigned to our module */
            $moduleIDObject = null;
            foreach ($moduleArray as $info) {
                if ($moduleName == $info->name) {
                    $moduleIDObject = $info;
                    break;
                }
            }
            $moduleID = $moduleIDObject->id;
            $this->ModuleManager->setMeta($moduleID, $meta);
            //Redirect with success message
            $url = explode("?",$_SERVER['REQUEST_URI']);
            header('Location:' . $url[0].'?scr=additionalsettings&msg=success');
            exit();
        }
        elseif($scr == 'importorder'){
            /* Load the Loader to fetch info of All the installed Module */
            Loader::loadModels($this, array("ModuleManager","Services","Packages","PackageOptions","Invoices"));

            if (!isset($this->Record)){
                Loader::loadComponents($this, ['Record']);
            }

            $errors = '';
            $storeOrderId = (isset($_POST['store_order_id']) ? $_POST['store_order_id'] : '' );
            $orderType = (isset($_POST['order_type']) ? $_POST['order_type'] : '' );
            $clientId = (isset($_POST['client']) ? $_POST['client'] : '' );
            $invoiceMethod = (isset($_POST['invoice_method']) ? $_POST['invoice_method'] : '');
            $sendEmail =  (isset($_POST['send_email']) ? true : false);

            $enteredServiceId = (isset($_POST['service_id']) ? $_POST['service_id'] : '' );

            if(!empty($storeOrderId)){
                $api = $this->getApi();
                $orderStatusReq = new order_status_request();
                $orderStatusReq->TheSSLStoreOrderID = $storeOrderId;
                $orderStatusResp = $api->order_status($orderStatusReq);

                if($orderStatusResp->AuthResponse->isError == false){
                    /* Call the function to get the module name */
                    $moduleName = $this->getName();

                    $moduleArray = $this->ModuleManager->getInstalled();
                    /* Retrieve the Company ID which was assigned to our module */
                    $moduleIDObject = null;
                    foreach ($moduleArray as $info) {
                        if ($moduleName == $info->name) {
                            $moduleIDObject = $info;
                            break;
                        }
                    }
                    $moduleID = $moduleIDObject->id;

                    $serviceArray = $this->Services->searchServiceFields($moduleID,'thesslstore_order_id',$storeOrderId);

                    if(isset($serviceArray[0]->id))
                    {
                        $existingServiceId = $serviceArray[0]->id;
                    }

                    if(!$existingServiceId) {
                        //Get Product query
                        $productQueryReq = new product_query_request();
                        $productQueryReq->ProductType = 0;
                        $productQueryReq->ProductCode = $orderStatusResp->ProductCode;
                        $productQueryResp = $api->product_query($productQueryReq);
                        if ($productQueryResp != null && $productQueryResp[0]->AuthResponse->isError == false) {
                            if ($orderType == 'new_order') {
                                if (!empty($clientId)) {
                                        //Get package id
                                        $package_meta = $this->Record->select(['package_id'])->from("package_meta")->where("key", "=", "thesslstore_product_code")->where("value", "=", $orderStatusResp->ProductCode)->fetch();
                                        $package_id = $package_meta->package_id;
                                        //Get package
                                        $package_data = $this->Packages->get($package_id);


                                        if (isset($package_id) && $package_data != NULL) {
                                            //Get pricing id
                                            $pricingArray = array();
                                            foreach ($package_data->pricing as $pricing) {
                                                if ($pricing->term == $orderStatusResp->Validity && $pricing->period == "month") {
                                                    $pricing_id = $pricing->pricing_id;
                                                }
                                            }
                                            //Get package pricing id
                                            $packagePricingData = $this->Record->select(['id'])->from("package_pricing")->where("package_id", "=", $package_id)->where("pricing_id", "=", $pricing_id)->fetch();
                                            $packagePrcingId = $packagePricingData->id;

                                            $values["pricing_id"] = $packagePrcingId;
                                            $values["client_id"] = $clientId;
                                            $values["status"] = "active";
                                            $values["use_module"] = 'false';
                                            $values["thesslstore_order_id"] = $orderStatusResp->TheSSLStoreOrderID;
                                            $values["thesslstore_token"] = $orderStatusResp->Token;

                                            $configoptionsArray = array();

                                            foreach ($package_data->option_groups as $confOptionGroup) {
                                                $confOptionGroupID = $confOptionGroup->id;
                                                //Get option id
                                                $configOptionGroupData = $this->Record->select(['option_id'])->from("package_option_group")->where("option_group_id", "=", $confOptionGroupID)->fetch();
                                                $configOptionID = $configOptionGroupData->option_id;
                                                //Get option Name
                                                $configOptionData = $this->PackageOptions-> getValues($configOptionID);
                                                if (strpos($configOptionData[0]->name, 'Additional SAN') !== false) {
                                                    $configoptionsArray[$configOptionData[0]->id] = $orderStatusResp->SANCount - $productQueryResp[0]->MinSan;
                                                } elseif (strpos($configOptionData[0]->name, 'Additional SERVER') !== false) {
                                                    $configoptionsArray[$configOptionData[0]->id] = $orderStatusResp->ServerCount - 1;
                                                }
                                            }
                                            $values["configoptions"] = $configoptionsArray;
                                            //place service in Blesta
                                            $addedServiceId = $this->Services->add($values, $package = null, $sendEmail );

                                            if (!empty($addedServiceId)) {
                                                //Get service info
                                                $serviceInfo = $this->Services->get($addedServiceId);
                                                if(isset($invoiceMethod) && $invoiceMethod == 'create')
                                                {
                                                    //Invoice Generation
                                                    $currency = $serviceInfo->package_pricing->currency;
                                                    $due_date = $serviceInfo->date_added;
                                                    $invoiceId = $this->Invoices->createFromServices($clientId,array($addedServiceId),$currency, $due_date,true,false);
                                                }
                                                else if(isset($invoiceMethod) && $invoiceMethod == 'append')
                                                {
                                                    $invoiceId = (isset($_POST['invoice']) ? $_POST['invoice'] : 0 );
                                                }
                                                else{
                                                    $invoiceId = 0;
                                                }

                                                try{
                                                    $this->Record->insert("sslstore_orders",
                                                        array('service_id' => $addedServiceId,
                                                            'package_id' => $package_id,
                                                            'invoice_id' => $invoiceId,
                                                            'store_order_id' => $orderStatusResp->TheSSLStoreOrderID,
                                                            'is_sandbox_order' => $this->is_sandbox_mode,
                                                            'created' => date('Y-m-d H:i:s')
                                                        )
                                                    );
                                                }
                                                catch(Exception $e){
                                                    $this->log('Database operation: Add Order Data', $e->getMessage(),'output', false);
                                                }

                                                try{
                                                    $this->Record->insert("sslstore_import_orders",
                                                        array('client_id' => $serviceInfo->client_id,
                                                            'service_id' => $addedServiceId,
                                                            'package_id' => $package_id,
                                                            'invoice_id' => $invoiceId,
                                                            'store_order_id' => $orderStatusResp->TheSSLStoreOrderID,
                                                            'package_name' => $serviceInfo->package->name,
                                                            'product_code' => $orderStatusResp->ProductCode,
                                                            'term' => $serviceInfo->package_pricing->term,
                                                            'period' => $serviceInfo->package_pricing->period,
                                                            'date_added' => $serviceInfo->date_added,
                                                            'date_renews' => $serviceInfo->date_renews
                                                        )
                                                    );
                                                }
                                                catch(Exception $e){
                                                    $this->log('Database operation: import order', $e->getMessage(),'output', false);
                                                }

                                                $url = explode("?",$_SERVER['REQUEST_URI']);
                                                header('Location:' . $url[0].'?scr=importorder&msg=success');
                                                exit();

                                            } else {
                                                $errors = "There is some issue while adding the service";
                                            }
                                        } else {
                                            $errors = "No product found in panel";
                                        }
                                } else {
                                    $errors = "Please select Client";
                                }
                            } elseif ($orderType == 'existing_order') {
                                //Map existing Blesta Service with TSS order
                                //Check this service id is exist or not
                                if(!empty($enteredServiceId)){
                                    //Get service info
                                    $serviceInfo = $this->Services->get($enteredServiceId);
                                    if(!empty($serviceInfo)){
                                        $packageId = $serviceInfo->package->id;
                                        //Get service info
                                        $packageInfo = $this->Packages->get($packageId);
                                        if($packageInfo->meta->thesslstore_product_code == $orderStatusResp->ProductCode){
                                            //check service is already linked store order id
                                            $storeOrderId = '';
                                            $sslOrderData = $this->Record->select(['store_order_id'])->from("sslstore_orders")->where("service_id", "=", $enteredServiceId)->fetch();
                                            $storeOrderId = $sslOrderData->store_order_id;
                                            if(empty($storeOrderId)){
                                                $invoice_data = $this->Record->select(['invoice_id'])->from("service_invoices")->where("service_id", "=", $enteredServiceId)->order(array("invoice_id" => "desc"))->fetch();
                                                try{
                                                    $this->Record->insert("sslstore_orders",
                                                        array('service_id' => $enteredServiceId,
                                                            'package_id' => $serviceInfo->package->id,
                                                            'invoice_id' => $invoice_data->invoice_id,
                                                            'store_order_id' => $orderStatusResp->TheSSLStoreOrderID,
                                                            'is_sandbox_order' => $this->is_sandbox_mode,
                                                            'created' => date('Y-m-d H:i:s')
                                                        )
                                                    );
                                                }
                                                catch(Exception $e){
                                                    $this->log('Database operation: Add Order Data', $e->getMessage(),'output', false);
                                                }

                                                try{
                                                    $this->Record->insert("sslstore_import_orders",
                                                        array('client_id' => $serviceInfo->client_id,
                                                            'service_id' => $enteredServiceId,
                                                            'package_id' => $serviceInfo->package->id,
                                                            'invoice_id' => $invoice_data->invoice_id,
                                                            'store_order_id' => $orderStatusResp->TheSSLStoreOrderID,
                                                            'package_name' => $serviceInfo->package->name,
                                                            'product_code' => $orderStatusResp->ProductCode,
                                                            'term' => $serviceInfo->package_pricing->term,
                                                            'period' => $serviceInfo->package_pricing->period,
                                                            'date_added' => $serviceInfo->date_added,
                                                            'date_renews' => $serviceInfo->date_renews
                                                        )
                                                    );
                                                }
                                                catch(Exception $e){
                                                    $this->log('Database operation: import order', $e->getMessage(),'output', false);
                                                }

                                                $this->Services->editField($enteredServiceId, array(
                                                    'key' => "thesslstore_order_id",
                                                    'value' => $orderStatusResp->TheSSLStoreOrderID,
                                                    'encrypted' => 0,
                                                ));

                                                $this->Services->editField($enteredServiceId, array(
                                                    'key' => "thesslstore_token",
                                                    'value' => $orderStatusResp->Token,
                                                    'encrypted' => 0,
                                                ));

                                                $this->Services->addField($enteredServiceId, array(
                                                    'key' => "thesslstore_fqdn",
                                                    'value' => $orderStatusResp->CommonName,
                                                    'encrypted' => 0,
                                                    'serialized' => 0
                                                ));

                                                try{
                                                    //update old record
                                                    $this->Record->where("service_id", "=", $enteredServiceId)->update("service_fields", array('serialized' => "0"));
                                                }
                                                catch(Exception $e){
                                                    $this->log('Database operation: update service fields', $e->getMessage(),'output', false);
                                                }

                                                $url = explode("?",$_SERVER['REQUEST_URI']);
                                                header('Location:' . $url[0].'?scr=importorder&msg=success');
                                                exit();
                                            }
                                            else{
                                                $errors = 'This service id is already linked with Store order id:'.$storeOrderId;
                                            }
                                        }
                                        else{
                                            $errors = 'Package does not match with Store';
                                        }
                                    }
                                    else{
                                        $errors = 'Invalid service id';
                                    }
                                }
                                else{
                                    $errors = 'Please enter Blesta service id';
                                }
                            } else {
                                $errors = 'Please select order type';
                            }
                        } else {
                            $errors = (isset($productQueryResp[0]->AuthResponse->Message[0]) ? $productQueryResp[0]->AuthResponse->Message[0] : 'Invalid Product Code');
                        }
                    }
                    else{
                        $errors = 'This Store Order Id already linked with service id:'.$existingServiceId;
                    }
                }
                else{
                    $errors = (isset($orderStatusResp->AuthResponse->Message[0]) ? $orderStatusResp->AuthResponse->Message[0] : 'Invalid Store Order Id') ;
                }
            }
            else{
                $errors = 'Please enter store order id';
            }
            if(!empty($errors))
            {
                //Redirect with success message
                $url = explode("?",$_SERVER['REQUEST_URI']);
                header('Location:' . $url[0].'?scr=importorder&error=true&errormsg='. $errors);
                exit();
            }
            die();

            $meta_fields = array("use_default_tech_details","thesslstore_tech_job_title", "thesslstore_tech_first_name", "thesslstore_tech_last_name",
                "thesslstore_tech_org_name", "thesslstore_tech_address", "thesslstore_tech_phone", "thesslstore_tech_email", "thesslstore_tech_city", "thesslstore_tech_state", "thesslstore_tech_country", "thesslstore_tech_zipcode","additional_days_for_neworder","additional_days_for_reneworder");
            $encrypted_fields = array();

            // Build the meta data
            $meta = array();
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = array(
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    );
                }
            }
            /* Call the function to get the module name */
            $moduleName = $this->getName();

            /* Load the Loader to fetch info of All the installed Module */
            Loader::loadModels($this, array("ModuleManager"));
            $moduleArray = $this->ModuleManager->getInstalled();
            /* Retrieve the Company ID which was assigned to our module */
            $moduleIDObject = null;
            foreach ($moduleArray as $info) {
                if ($moduleName == $info->name) {
                    $moduleIDObject = $info;
                    break;
                }
            }
            $moduleID = $moduleIDObject->id;
            $this->ModuleManager->setMeta($moduleID, $meta);
            //Redirect with success message
            $url = explode("?",$_SERVER['REQUEST_URI']);
            header('Location:' . $url[0].'?scr=additionalsettings&msg=success');
            exit();
        }
    }

    /**
     * Use this function to set up product pricing with the desired margin
     */
    private function setupPrice($price,$margin){
        $givenPrice = ($price+$price*$margin/100);
        $finalPrice = number_format($givenPrice,2, '.', '');

        return $finalPrice;
    }

    /*------------------ Currency Converter Function ---------------------------*/
    /**
     * Use this function to convert the pricing
     */
    private function currecncy_converter($productprice, $currency_rate, $api_currency_rate){

        $final_price = (($productprice * $currency_rate)/$api_currency_rate);

        return number_format($final_price, 2, '.', '');
    }

    /**
     * Edits the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being updated.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of module info to update
     * @return array A numerically indexed array of meta fields for the module row containing:
     * 	- key The key for this meta field
     * 	- value The value for this key
     * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function editModuleRow($module_row, array &$vars) {
        $scr = isset($_GET['scr']) ? $_GET['scr'] : '';
        if($scr == 'editcredential') {
            $meta_fields = array("thesslstore_reseller_name","api_partner_code_live", "api_auth_token_live", "api_partner_code_test",
                "api_auth_token_test", "api_mode");
            $encrypted_fields = array("api_partner_code_live", "api_auth_token_live", "api_partner_code_test", "api_auth_token_test");

            // Validate module row
            if ($this->Input->validates($vars)) {
                // Build the meta data for this row
                $meta = array();
                foreach ($vars as $key => $value) {
                    if (in_array($key, $meta_fields)) {
                        $meta[] = array(
                            'key' => $key,
                            'value' => $value,
                            'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                        );
                    }
                }
                return $meta;
            }
        }
    }
    /**
     * Returns all fields used when adding/editing a package, including any
     * javascript to execute when the page is rendered with these fields.
     *
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render as well as any additional HTML markup to include
     */
    public function getPackageFields($vars=null) {

        Loader::loadHelpers($this, array("Form", "Html"));

        $fields = new ModuleFields();

        $product_data = $this->getProducts();

        $product_codes[''] = Language::_("ThesslstoreModule.please_select", true);
        $products = array();
        foreach($product_data as $product){
            $product_codes[$product->ProductCode] = $product->ProductName;

            $data = array();
            $data['thesslstore_product_code'] = $product->ProductCode;
            $data['thesslstore_vendor_name'] = $product->VendorName;
            $data['thesslstore_is_code_signing'] = ($product->isCodeSigning == true) ? 'y' : 'n';
            $data['thesslstore_min_san'] = $product->MinSan;
            $data['thesslstore_is_scan_product'] = ($product->isScanProduct == true) ? 'y' : 'n';

            $validation_type = "N/A";
            if($product->isDVProduct == true)
                $validation_type = 'DV';
            elseif($product->isOVProduct == true)
                $validation_type = 'OV';
            elseif($product->isEVProduct == true)
                $validation_type = 'EV';

            $data['thesslstore_validation_type'] = $validation_type;
            $products[] = $data;
        }

        $products = json_encode($products);

        // Show nodes, and set javascript field toggles
        $this->Form->setOutput(true);

        // Set the product as a selectable option
        $thesslstore_product_code = $fields->label(Language::_("ThesslstoreModule.package_fields.product_code", true), "thesslstore_product_code");
        $thesslstore_product_code->attach($fields->fieldSelect("meta[thesslstore_product_code]", $product_codes,
            $this->Html->ifSet($vars->meta['thesslstore_product_code']), array('id' => "thesslstore_product_code","onchange" => "javascript:get_ssl_meta(this.value)")));
        $fields->setField($thesslstore_product_code);
        unset($thesslstore_product_code);

        $field_thesslstore_vendor_name = $fields->fieldHidden( "meta[thesslstore_vendor_name]",$this->Html->ifSet($vars->meta['thesslstore_vendor_name']),array('id' => "thesslstore_vendor_name") );
        $fields->setField($field_thesslstore_vendor_name);
        unset($field_thesslstore_vendor_name);

        $field_thesslstore_is_code_signing = $fields->fieldHidden( "meta[thesslstore_is_code_signing]",$this->Html->ifSet($vars->meta['thesslstore_is_code_signing']),array('id' => "thesslstore_is_code_signing") );
        $fields->setField($field_thesslstore_is_code_signing);
        unset($field_thesslstore_is_code_signing);

        $field_thesslstore_min_san = $fields->fieldHidden( "meta[thesslstore_min_san]",$this->Html->ifSet($vars->meta['thesslstore_min_san']),array('id' => "thesslstore_min_san") );
        $fields->setField($field_thesslstore_min_san);
        unset($field_thesslstore_min_san);

        $field_thesslstore_is_scan_product = $fields->fieldHidden( "meta[thesslstore_is_scan_product]",$this->Html->ifSet($vars->meta['thesslstore_is_scan_product']),array('id' => "thesslstore_is_scan_product") );
        $fields->setField($field_thesslstore_is_scan_product);
        unset($field_thesslstore_is_scan_product);

        $field_thesslstore_validation_type = $fields->fieldHidden( "meta[thesslstore_validation_type]",$this->Html->ifSet($vars->meta['thesslstore_validation_type']),array('id' => "thesslstore_validation_type") );
        $fields->setField($field_thesslstore_validation_type);
        unset($field_thesslstore_validation_type);

        $fields->setHtml("
            <script type=\"text/javascript\">
                function get_ssl_meta(code){
                   var ssl_product = {$products};
                   for(var i=0;i<ssl_product.length;i++){
                        if(ssl_product[i].thesslstore_product_code == code){
                            $('input#thesslstore_vendor_name').val(ssl_product[i].thesslstore_vendor_name);
                            $('input#thesslstore_is_code_signing').val(ssl_product[i].thesslstore_is_code_signing);
                            $('input#thesslstore_min_san').val(ssl_product[i].thesslstore_min_san);
                            $('input#thesslstore_is_scan_product').val(ssl_product[i].thesslstore_is_scan_product);
                            $('input#thesslstore_validation_type').val(ssl_product[i].thesslstore_validation_type);
                            break;
                        }
                    }
                }
            </script>
        ");

        return $fields;
    }

    /**
     * Adds the service to the remote server. Sets Input errors on failure,
     * preventing the service from being added.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of the service being added (if the current service is an addon service and parent service has already been provisioned)
     * @param string $status The status of the service being added. These include:
     * 	- active
     * 	- canceled
     * 	- pending
     * 	- suspended
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     * 	- key The key for this meta field
     * 	- value The value for this key
     * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addService($package, array $vars=null, $parent_package=null, $parent_service=null, $status="pending") {
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }


        $thesslstore_order_id = '';
        $token = '';

        if($vars["use_module"] == "true") {
            //Get Custom Order ID
            $tssCustomOrderID = $this->customOrderId($vars["service_id"]);

            $api = $this->getApi();

            $invite_order_req = new order_inviteorder_request();
            $invite_order_req->AddInstallationSupport = false;
            $invite_order_req->CustomOrderID = $tssCustomOrderID;
            $invite_order_req->EmailLanguageCode = 'EN';
            $invite_order_req->PreferVendorLink = false;
            $invite_order_req->ProductCode = $package->meta->thesslstore_product_code;

            $additional_server = (isset($vars['configoptions']['additional_server']) ? $vars['configoptions']['additional_server'] : 0);

            $invite_order_req->ServerCount = 1 + $additional_server;
            $invite_order_req->ValidityPeriod = 12; //Months

            foreach($package->pricing as $pricing) {
                if ($pricing->id == $vars['pricing_id']) {
                    if($pricing->period == 'month')
                        $invite_order_req->ValidityPeriod = $pricing->term;
                    elseif($pricing->period == 'year')
                        $invite_order_req->ValidityPeriod = $pricing->term * 12;
                    break;
                }
            }
            $additional_san = (isset($vars['configoptions']['additional_san']) ? $vars['configoptions']['additional_san'] : 0);
            $invite_order_req->ExtraSAN = $package->meta->thesslstore_min_san + $additional_san;


            $this->log($this->api_partner_code . "|ssl-invite-order", serialize($invite_order_req), "input", true);
            if(strtoupper($package->meta->thesslstore_vendor_name) == 'DIGICERT'){
                $result = $this->parseResponse($api->digicert_inviteorder($invite_order_req));
            }
            else{
                $result = $this->parseResponse($api->order_inviteorder($invite_order_req));
            }

            if(empty($result)){
                return;
            }

            if(!empty($result->TheSSLStoreOrderID)){
                $thesslstore_order_id = $result->TheSSLStoreOrderID;
                $token = $result->Token;

                //make entry in database
                //get invoice id
                $invoice_data = $this->Record->select(['invoice_id'])->from("service_invoices")->where("service_id", "=", $vars['service_id'])->order(array("invoice_id"=>"desc"))->fetch();
                if($invoice_data){
                    try{
                        $this->Record->insert("sslstore_orders",
                            array('service_id' => $vars['service_id'],
                                'package_id' => $package->id,
                                'invoice_id' => $invoice_data->invoice_id,
                                'store_order_id' => $thesslstore_order_id,
                                'is_sandbox_order' => $this->is_sandbox_mode,
                                'created' => date('Y-m-d H:i:s')
                            )
                        );
                    }
                    catch(Exception $e){
                        $this->log('Database operation: Add Service', $e->getMessage(),'output', false);
                    }
                }


            }
            else {
                $this->Input->setErrors(array('api' => array('internal' => 'No OrderID')));
                return;
            }
        }
        else{
            $thesslstore_order_id = $vars['thesslstore_order_id'];
            $token = $vars['thesslstore_token'];
        }

        // Return service fields
        return array(
            array(
                'key' => "thesslstore_order_id",
                'value' => $thesslstore_order_id,
                'encrypted' => 0
            ),
            array(
                'key' => "thesslstore_token",
                'value' => $token,
                'encrypted' => 0
            )
        );
    }

    /**
     * Allows the module to perform an action when the service is ready to renew.
     * Sets Input errors on failure, preventing the service from renewing.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of the service being renewed (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
     * 	- key The key for this meta field
     * 	- value The value for this key
     * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function renewService($package, $service, $parent_package=null, $parent_service=null){
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }

        $service_fields = $this->serviceFieldsToObject($service->fields);

        $service_meta = array();

        $old_thesslstore_order_id = $service_fields->thesslstore_order_id;

        $order_status_response = $this->getSSLOrderStatus($old_thesslstore_order_id, $package->meta->thesslstore_vendor_name);

        //Get Custom Order ID
        $tssCustomOrderID = $this->customOrderId($service->id);
        $tssCustomOrderID = $tssCustomOrderID."-renew-".$old_thesslstore_order_id;

        //Placed Invite order first
        $api = $this->getApi();

        $invite_order_req = new order_inviteorder_request();
        $invite_order_req->AddInstallationSupport = false;
        $invite_order_req->CustomOrderID = $tssCustomOrderID;
        $invite_order_req->EmailLanguageCode = 'EN';
        $invite_order_req->PreferVendorLink = false;
        $invite_order_req->ProductCode = $package->meta->thesslstore_product_code;

        //get additional server and san value
        $additional_san = 0;
        $additional_server = 0;
        foreach($service->options as $option){
            if($option->option_name == 'additional_san'){
                $additonal_san = $option->qty;
            }
            if($option->option_name == 'additional_server'){
                $additonal_server = $option->qty;
            }
        }

        $server_count = 1 + $additional_server;
        $invite_order_req->ServerCount = $server_count;
        $validity_period = 12; //Months

        foreach($package->pricing as $pricing){
            if($pricing->id == $service->pricing_id){
                if($pricing->period == 'month')
                    $validity_period = $pricing->term;
                elseif($pricing->period == 'year')
                    $validity_period = $pricing->term * 12;
                break;
            }
        }

        $invite_order_req->ValidityPeriod = $validity_period;
        $invite_order_req->ExtraSAN = $package->meta->thesslstore_min_san + $additional_san;


        $this->log($this->api_partner_code . "|ssl-invite-renew-order", serialize($invite_order_req), "input", true);
        if($package->meta->thesslstore_vendor_name == 'DIGICERT'){
            $result = $this->parseResponse($api->digicert_inviteorder($invite_order_req));
        }
        else{
            $result = $this->parseResponse($api->order_inviteorder($invite_order_req));
        }

        if(empty($result)) {
            return null;
        }

        if(!empty($result->TheSSLStoreOrderID)){
            $thesslstore_order_id = $result->TheSSLStoreOrderID;
            $thesslstore_token = $result->Token;

            //make entry in database
            //get invoice id
            $invoice_data = $this->Record->select(['invoice_id'])->from("service_invoices")->where("service_id", "=", $service->id)->order(array("invoice_id"=>"desc"))->fetch();
            if($invoice_data){
                try{
                    $this->Record->insert("sslstore_orders",
                        array('service_id' => $service->id,
                            'package_id' => $package->id,
                            'invoice_id' => $invoice_data->invoice_id,
                            'store_order_id' => $thesslstore_order_id,
                            'renew_from' => $old_thesslstore_order_id,
                            'is_sandbox_order' => $this->is_sandbox_mode,
                            'created' => date('Y-m-d H:i:s')
                        )
                    );

                    //update old record
                    $this->Record->where("store_order_id", "=", $old_thesslstore_order_id)->update("sslstore_orders", array('renew_to' => $thesslstore_order_id));
                }
                catch(Exception $e){
                    $this->log('Database operation: Renew Service', $e->getMessage(),'output', false);
                }
            }

            $service_meta[] = array(
                'key' => "thesslstore_order_id",
                'value' => $thesslstore_order_id,
                'encrypted' => 0
            );
            $service_meta[] = array(
                'key' => "thesslstore_token",
                'value' => $thesslstore_token,
                'encrypted' => 0
            );

            $service_meta[] = array(
                'key' => "thesslstore_renew_from",
                'value' => $old_thesslstore_order_id,
                'encrypted' => 0
            );
            $send_invite_order_email = true;

            //if CSR is found then place full order.
            if(isset($service_fields->thesslstore_csr) && !empty($service_fields->thesslstore_csr)){

                $contact = new contact();
                $contact->AddressLine1 = $order_status_response->AdminContact->AddressLine1;
                $contact->AddressLine2 = $order_status_response->AdminContact->AddressLine2;
                $contact->City = $order_status_response->AdminContact->City;
                $contact->Region = $order_status_response->AdminContact->Region;
                $contact->Country = $order_status_response->AdminContact->Country;
                $contact->Email = $order_status_response->AdminContact->Email;
                $contact->Fax = $order_status_response->AdminContact->Fax;
                $contact->FirstName = $order_status_response->AdminContact->FirstName;
                $contact->LastName = $order_status_response->AdminContact->LastName;
                $contact->OrganizationName = $order_status_response->AdminContact->OrganizationName;
                $contact->Phone = $order_status_response->AdminContact->Phone;
                $contact->PostalCode = $order_status_response->AdminContact->PostalCode;
                $contact->Title = $order_status_response->AdminContact->Title;

                $tech_contact = new contact();
                $tech_contact->AddressLine1 = $order_status_response->TechnicalContact->AddressLine1;
                $tech_contact->AddressLine2 = $order_status_response->TechnicalContact->AddressLine2;
                $tech_contact->City = $order_status_response->TechnicalContact->City;
                $tech_contact->Region = $order_status_response->TechnicalContact->Region;
                $tech_contact->Country = $order_status_response->TechnicalContact->Country;
                $tech_contact->Email = $order_status_response->TechnicalContact->Email;
                $tech_contact->Fax = $order_status_response->TechnicalContact->Fax;
                $tech_contact->FirstName = $order_status_response->TechnicalContact->FirstName;
                $tech_contact->LastName = $order_status_response->TechnicalContact->LastName;
                $tech_contact->OrganizationName = $order_status_response->TechnicalContact->OrganizationName;
                $tech_contact->Phone = $order_status_response->TechnicalContact->Phone;
                $tech_contact->PostalCode = $order_status_response->TechnicalContact->PostalCode;
                $tech_contact->Title =$order_status_response->TechnicalContact->Title;

                $org_country = $order_status_response->Country;

                //get organisation country
                /*For the comodo product ,if user do not pass org country name in new order
                then API take country name from CSR which is always full name
                while in renewal it will take the org country name from order status that will give an error
                " ErrorCode:-9009|Message:Vendor returns error:the value of the 'apprepcountryname' argument is invalid!"
                */

                foreach($this->getCountryList() as $code => $name){
                    if($org_country == $code || $org_country == $name){
                        $org_country = $code;
                        break;
                    }
                }

                $new_order = new order_neworder_request();


                $new_order->AddInstallationSupport = false;
                $new_order->AdminContact = $contact;
                $new_order->CSR = $service_fields->thesslstore_csr;
                $new_order->DomainName = $order_status_response->CommonName;
                $new_order->RelatedTheSSLStoreOrderID = '';
                $new_order->DNSNames = explode(',',$order_status_response->DNSNames);
                $new_order->EmailLanguageCode = 'EN';
                $new_order->ExtraProductCodes = '';
                if($package->meta->thesslstore_vendor_name != 'DIGICERT'){
                    $new_order->OrganizationInfo->DUNS = $order_status_response->DUNS;
                    $new_order->OrganizationInfo->Division = $order_status_response->OrganizationalUnit;
                    $new_order->OrganizationInfo->IncorporatingAgency = '';
                    $new_order->OrganizationInfo->JurisdictionCity = $order_status_response->Locality;
                    $new_order->OrganizationInfo->JurisdictionCountry = $org_country;
                    $new_order->OrganizationInfo->JurisdictionRegion = $order_status_response->State;
                    $new_order->OrganizationInfo->OrganizationName = $order_status_response->Organization;
                    $new_order->OrganizationInfo->RegistrationNumber = '';
                    $new_order->OrganizationInfo->OrganizationAddress->AddressLine1 = $order_status_response->OrganizationAddress;
                    $new_order->OrganizationInfo->OrganizationAddress->AddressLine2 = '';
                    $new_order->OrganizationInfo->OrganizationAddress->AddressLine3 = '';
                    $new_order->OrganizationInfo->OrganizationAddress->City = $order_status_response->Locality;
                    $new_order->OrganizationInfo->OrganizationAddress->Country = $org_country;
                    $new_order->OrganizationInfo->OrganizationAddress->Fax = '';
                    $new_order->OrganizationInfo->OrganizationAddress->LocalityName = '';
                    $new_order->OrganizationInfo->OrganizationAddress->Phone = $order_status_response->OrganizationPhone;
                    $new_order->OrganizationInfo->OrganizationAddress->PostalCode = $order_status_response->OrganizationPostalcode;
                    $new_order->OrganizationInfo->OrganizationAddress->Region = $order_status_response->State;
                }
                $new_order->ProductCode = $package->meta->thesslstore_product_code;
                $new_order->ReserveSANCount = $additional_san;
                $new_order->ServerCount = $server_count;
                $new_order->SpecialInstructions = '';
                $new_order->TechnicalContact = $tech_contact;
                $new_order->ValidityPeriod = $validity_period; //number of months
                $new_order->WebServerType = $order_status_response->WebServerType;
                $new_order->isCUOrder = false;
                $new_order->isRenewalOrder = true;
                $new_order->isTrialOrder = false;
                $new_order->SignatureHashAlgorithm = $order_status_response->SignatureHashAlgorithm;

                if (!empty($order_status_response->AuthFileContent)) {
                    $new_order->FileAuthDVIndicator = true;
                    $new_order->ApproverEmail = $order_status_response->ApproverEmail;
                } elseif (!empty($order_status_response->CNAMEAuthValue)) {
                    $new_order->CNAMEAuthDVIndicator = true;
                    $new_order->ApproverEmail = $order_status_response->ApproverEmail;
                } else {
                    $new_order->FileAuthDVIndicator = false;
                    $new_order->ApproverEmail = $order_status_response->ApproverEmail;
                }

                //Pass additional days for renew order, based on the admin side settings
                $additionalDays = 0;
                $moduleID = $package->module_id;
                $meta = $this->ModuleManager->getMeta($moduleID,'additional_days_for_reneworder');
                if(isset($meta->additional_days_for_reneworder))
                {
                    $additionalDays = $meta->additional_days_for_reneworder;
                }
                if($order_status_response->ProductCode == 'freessl' || $additionalDays <=0){
                    $new_order->isRenewalOrder = false;
                }
                else{
                    $new_order->isRenewalOrder = true;
                    $new_order->RenewalDays = $additionalDays;
                }

                if(strtoupper($order_status_response->VendorName) == 'COMODO' || strtoupper($order_status_response->VendorName) == 'SECTIGO'){
                    $new_order->CSRUniqueValue = date('YmdHisa');
                }

                if($package->meta->thesslstore_vendor_name == 'DIGICERT'){
                    $new_order->PreOrganizationId = $order_status_response->PreOrganizationId;
                }

                //Place full order
                $api_with_token = $this->getApi(null,null,'',$IsUsedForTokenSystem = true, $thesslstore_token);
                $this->log($this->api_partner_code . "|ssl-full-renew-order", serialize($new_order), "input", true);
                //waiting time
                sleep(12);
                if($package->meta->thesslstore_vendor_name == 'DIGICERT'){
                    $new_order_resp = $this->parseResponse($api_with_token->digicert_new_order($new_order),$ignore_error = true);
                }
                else{
                    $new_order_resp = $this->parseResponse($api_with_token->order_neworder($new_order),$ignore_error = true);
                }

                if(isset($new_order_resp->AuthResponse->isError) && $new_order_resp->AuthResponse->isError == false){
                    $send_invite_order_email = false;
                }
            }

            //send email to customer when only invite order is placed.
            if($send_invite_order_email){
                //get client data to prefiiled data
                $this->sendInviteOrderEmail($service, $package, $service_meta);
            }

        }
        else {
            $this->Input->setErrors(array('api' => array('internal' => 'No OrderID')));
            return null;
        }

        // Return service fields
        if(!empty($service_meta)){

            //added old meta like csr and fqdn etc.
            foreach($service->fields as $field){
                $already_added = false;
                foreach($service_meta as $meta){
                    if($field->key == $meta['key']){
                        $already_added = true;
                        break;
                    }

                }
                if($already_added == false) {
                    $service_meta[] = array(
                        'key' => $field->key,
                        'value' => $field->value,
                        'encrypted' => $field->encrypted,
                        'serialized' => $field->serialized
                    );
                }
            }

            return $service_meta;
        }
        return null;
    }

    /**
     * Cancels the service on the remote server. Sets Input errors on failure,
     * preventing the service from being canceled.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent service of the service being canceled (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically indexed array of meta fields to be stored for this service containing:
     * 	- key The key for this meta field
     * 	- value The value for this key
     * 	- encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function cancelService($package, $service, $parent_package=null, $parent_service=null){
        /*
         * This method will called only if user has selected "Immediately" cancellation.
         */
        if (!isset($this->Record)){
            Loader::loadComponents($this, ['Record']);
        }
        if($service->status == 'active'){
            // Get the service fields
            $service_fields = $this->serviceFieldsToObject($service->fields);
            // Get the Order ID
            $orderID = $service_fields->thesslstore_order_id;

            //get last generated invoice id of current service
            $last_invoice = $this->Record->select(['invoice_id'])->from("service_invoices")->where("service_id", "=", $service->id)->order(array("invoice_id"=>"desc"))->fetch();

            //get invoice id for current order
            $current_invoice = $this->Record->select(['invoice_id'])->from("sslstore_orders")->where("store_order_id", "=", $orderID)->fetch();

            /*
             * If the customer does not pay their renewal invoice and is submitted for cancellation it errors out when processing with the error message "ErrorCode:-9032|Message:Certificate cannot cancel after 30 days of activation."
             * Below condition prevent above errors.
             */
            if($last_invoice->invoice_id  == $current_invoice->invoice_id){

                //Raise Refund Request
                $api = $this->getApi();
                $refundReq = new order_refundrequest_request();
                $refundReq->RefundReason = 'Requested by User!';
                $refundReq->TheSSLStoreOrderID = $orderID;

                $this->log($this->api_partner_code . "|ssl-refund-request", serialize($refundReq), "input", true);
                if($package->meta->thesslstore_vendor_name == 'DIGICERT'){
                    $refundRes = $api->digicert_refundrequest($refundReq);
                }
                else{
                    $refundRes = $api->order_refundrequest($refundReq);
                }
                if(!$refundRes->AuthResponse->Message[0]){
                    $errorMessage = $refundRes->AuthResponse->Message;
                }
                else{
                    $errorMessage = $refundRes->AuthResponse->Message[0];
                }

                if($refundRes != NULL && $refundRes->AuthResponse->isError == false){
                    $this->log($this->api_partner_code . "|ssl-refund-response", serialize($refundRes), "output", true);
                    return null;
                }
                else{
                    $this->log($this->api_partner_code . "|ssl-refund-response", serialize($refundRes), "output", false);
                    $this->Input->setErrors(array('invalid_action' => array('internal' => $errorMessage)));
                    return;
                }
            }
        }
        return null;
    }

    /**
     * Retrieves a list of products with all the information
     *
     * @param TheSSLStoreApi $api the API to use
     * @param stdClass $row A stdClass object representing a single reseller (optional, required when Module::getModuleRow() is unavailable)
     * @return array A list of products
     */
    public function getProducts() {

        $api = $this->getApi();

        $product_query_request = new product_query_request();
        $product_query_request->ProductType = 0;
        $product_query_request->NeedSortedList = true;

        $this->log($this->api_partner_code . "|ssl-products", serialize($product_query_request), "input", true);
        $productsArray = $this->parseResponse($api->product_query($product_query_request));

        return $productsArray;
    }

    /**
     * Returns all tabs to display to a client when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
     */
    public function getClientTabs($package) {

        return array(
            'tabClientCertDetails' => array('name' => Language::_("ThesslstoreModule.tab_CertDetails", true),'icon' => 'fa fa-bars'),
            'tabClientGenerateCert' => array('name' => Language::_("ThesslstoreModule.tab_GenerateCert", true),'icon' => 'fa fa-cogs'),
            'tabClientDownloadCertificate' =>array('name' => Language::_("ThesslstoreModule.tab_DownloadCertificate", true), 'icon' => 'fa fa-certificate'),
            'tabClientDownloadAuthFile' => array('name' => Language::_("ThesslstoreModule.tab_DownloadAuthFile", true), 'href'=>'#', 'class' => 'hidden ssltab','icon' => 'fa fa-file'),
            'tabClientChangeApproverEmail' => array('name' => Language::_("ThesslstoreModule.tab_ChangeApproverEmail", true),'icon' => 'fa fa-exchange'),
            'tabClientResendApproverEmail' => array('name' => Language::_("ThesslstoreModule.tab_ResendApproverEmail",true), 'href' => '#', 'class' => 'hidden','icon' => 'fa fa-refresh'),
            'tabClientReissueCert' => array('name' => Language::_("ThesslstoreModule.tab_ReissueCert",true),'icon' => 'fa fa-repeat')
        );
    }

    /**
     * Returns all tabs to display to an admin when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title. Example: array('methodName' => "Title", 'methodName2' => "Title2")
     */
    public function getAdminTabs($package) {
        return array(
            'tabAdminManagementAction' => Language::_("ThesslstoreModule.tab_AdminManagementAction",true),
        );
    }

    /**
     * Client Certificate Details tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientCertDetails($package, $service, array $get=null, array $post=null, array $files=null) {
        if($service->status == 'active') {
            $this->view = new View("tab_client_cert_details", "default");
            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));

            // Get the service fields
            $service_fields = $this->serviceFieldsToObject($service->fields);

            $order_resp = $this->getSSLOrderStatus($service_fields->thesslstore_order_id, $package->meta->thesslstore_vendor_name);

            //Update Order Info
            $update_order_data_resp = $this->updateOrderData($order_resp, $service);


            $auth_details = $this->getAuthDetails($order_resp);

            if($order_resp) {
                $vendor_name = $package->meta->thesslstore_vendor_name;
                $is_code_signing = $package->meta->thesslstore_is_code_signing;
                $is_scan_product = $package->meta->thesslstore_is_scan_product;

                //Certificate Details
                $certificate['order_status'] = strtoupper($order_resp->OrderStatus->MajorStatus);
                $certificate['store_order_id'] = $service_fields->thesslstore_order_id;
                $certificate['renew_from'] = (isset($service_fields->thesslstore_renew_from) ? $service_fields->thesslstore_renew_from : '' );
                $certificate['vendor_order_id'] = $order_resp->VendorOrderID;
                $certificate['vendor_status'] = $order_resp->OrderStatus->MinorStatus;
                $certificate['vendor_name'] = $vendor_name;
                $certificate['token'] = $order_resp->Token;
                $certificate['ssl_start_date'] = $this->getFormattedDate($order_resp->CertificateStartDateInUTC);
                $certificate['ssl_end_date'] = $this->getFormattedDate($order_resp->CertificateEndDateInUTC);
                $certificate['domains'] = (!empty($order_resp->CommonName) ? $order_resp->CommonName : '-' );
                $certificate['additional_domains'] = $order_resp->DNSNames;
                $certificate['siteseal_url'] = $order_resp->SiteSealurl;
                $certificate['verification_email'] = $order_resp->ApproverEmail;
                $certificate['verification_type'] = '';
                if($order_resp->AuthFileName != '' && $order_resp->AuthFileContent != ''){
                    $certificate['verification_type'] = 'file';
                }
                elseif($order_resp->ApproverEmail != ''){
                    $certificate['verification_type'] = 'email';
                }


                //Certificate Admin Details
                $certificate['admin_title'] = (!empty($order_resp->AdminContact->Title) ? $order_resp->AdminContact->Title : '-');
                $certificate['admin_first_name'] = $order_resp->AdminContact->FirstName;
                $certificate['admin_last_name'] = $order_resp->AdminContact->LastName;
                $certificate['admin_email'] = $order_resp->AdminContact->Email;
                $certificate['admin_phone'] = $order_resp->AdminContact->Phone;

                //Certificate Technical Details
                $certificate['tech_title'] = (!empty($order_resp->TechnicalContact->Title) ? $order_resp->TechnicalContact->Title : '-');
                $certificate['tech_first_name'] = $order_resp->TechnicalContact->FirstName;
                $certificate['tech_last_name'] = $order_resp->TechnicalContact->LastName;
                $certificate['tech_email'] = $order_resp->TechnicalContact->Email;
                $certificate['tech_phone'] = $order_resp->TechnicalContact->Phone;

                if(strtoupper($order_resp->VendorName) == 'DIGICERT'){
                    $certificate['org_name'] = $order_resp->Organization;
                    $certificate['org_id'] = $order_resp->PreOrganizationId;
                }

                $certificate['generation_link'] = $this->base_uri . "services/manage/" . ($service->id) . "/tabClientGenerateCert/";
                /* Provide central API link for CERTUM products*/
                $this->view->set("link_target", '');
                if ($vendor_name == 'CERTUM' || $is_scan_product == 'y' || $is_code_signing == 'y') {
                    $certificate['generation_link'] = $order_resp->TinyOrderLink;
                    $this->view->set("link_target", '_blank');
                }
                $this->view->set("service", $service);
                $this->view->set("certificate", (object)$certificate);
                $this->view->set("auth_details", $auth_details);
                $this->view->set("get_approver_email_url", $this->base_uri . "services/manage/" .$service->id. "/tabClientResendApproverEmail/");
                $this->view->set("vendor_name", strtoupper($order_resp->VendorName));
                $this->view->set("token", $order_resp->Token);

                return $this->view->fetch();
            }
        }
        else {
            $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.invalid_service_status", true))));
            return;
        }
    }
    private function validateGenerateCertStep1($package, $vars, $san_count, $need_to_create_org)
    {
        // Set rules
        $rules = array(
            'thesslstore_csr' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_csr.empty", true)
                ),
                'valid' => array(
                    'rule' => array(array($this, "validateCSR"), $package->meta->thesslstore_product_code, true),
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_csr.valid", true)
                )
            ),
            'thesslstore_webserver_type' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_webserver_type.empty", true)
                )
            ),
            /*'thesslstore_auth_method' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_auth_method.empty", true)
                )
            ),*/
            'thesslstore_signature_algorithm' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_signature_algorithm.empty", true)
                )
            )
        );
        if($san_count > 0){
            /*validate Additional SAN (Minimum 1 Additional SAN should be passed for SAN Enabled Product*/
            $rules['thesslstore_additional_san'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_additional_san.empty", true)
                )
            );
        }

        //DigiCert
        if($package->meta->thesslstore_vendor_name != 'DIGICERT'){
            $rules['thesslstore_tech_first_name'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_tech_first_name.empty", true)
                )
            );
            $rules['thesslstore_tech_last_name'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_tech_last_name.empty", true)
                )
            );
            $rules['thesslstore_tech_email'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_tech_email.empty", true)
                )
            );
            $rules['thesslstore_tech_phone'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_tech_phone.empty", true)
                )
            );
        }

        //existing organization
        if($package->meta->thesslstore_vendor_name == 'DIGICERT' && $need_to_create_org == false){
            $rules['thesslstore_org_id'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_org_id.empty", true)
                )
            );
        }

        //new organisation or non DigiCert product
        if($package->meta->thesslstore_vendor_name != 'DIGICERT' || ($package->meta->thesslstore_vendor_name == 'DIGICERT' && $need_to_create_org)){
            $rules['thesslstore_admin_first_name'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_admin_first_name.empty", true)
                )
            );
            $rules['thesslstore_admin_last_name'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_admin_last_name.empty", true)
                )
            );
            $rules['thesslstore_admin_email'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_admin_email.empty", true)
                )
            );
            $rules['thesslstore_admin_phone'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_admin_phone.empty", true)
                )
            );
            $rules['thesslstore_org_name'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_org_name.empty", true)
                )
            );
            $rules['thesslstore_org_division'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_org_division.empty", true)
                )
            );
            $rules['thesslstore_admin_address1'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_admin_address1.empty", true)
                )
            );
            $rules['thesslstore_admin_city'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_admin_city.empty", true)
                )
            );
            $rules['thesslstore_admin_state'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_admin_state.empty", true)
                )
            );
            $rules['thesslstore_admin_country'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_admin_country.empty", true)
                )
            );
            $rules['thesslstore_admin_zip'] = array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_admin_zip.empty", true)
                )
            );

        }

        $this->Input->setRules($rules);
        return $this->Input->validates($vars);
    }

    private function validateReissueCert($package, $vars, $san_count){
        // Set rules
        $rules = array(
            'thesslstore_csr' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_csr.empty", true)
                ),
                'valid' => array(
                    'rule' => array(array($this, "validateCSR"), $package->meta->thesslstore_product_code, true),
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_csr.valid", true)
                )
            ),
            'thesslstore_webserver_type' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_webserver_type.empty", true)
                )
            ),
            'thesslstore_signature_algorithm' => array(
                'empty' => array(
                    'rule' => "isEmpty",
                    'negate' => true,
                    'message' => Language::_("ThesslstoreModule.!error.thesslstore_signature_algorithm.empty", true)
                )
            )
        );


        $this->Input->setRules($rules);
        return $this->Input->validates($vars);
    }

    /**
     * Client Generate Certificate tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */

    public function tabClientGenerateCert($package, $service, array $get=null, array $post=null, array $files=null){

        if($service->status == 'active') {


            $service_fields = $this->serviceFieldsToObject($service->fields);
            $thesslstore_order_id = $service_fields->thesslstore_order_id;
            $product_code = $package->meta->thesslstore_product_code;
            $vendor_name = $package->meta->thesslstore_vendor_name;
            $is_code_signing = $package->meta->thesslstore_is_code_signing;
            $is_scan_product = $package->meta->thesslstore_is_scan_product;
            $validation_type = $package->meta->thesslstore_validation_type;
            $order_resp = $this->getSSLOrderStatus($thesslstore_order_id, $package->meta->thesslstore_vendor_name);
            $use_central_api = false;
            $cert_generation_link = "";
            if ($vendor_name == 'CERTUM' || $is_code_signing == 'y' || $is_scan_product == 'y') {
                $cert_generation_link = $order_resp->TinyOrderLink;
                $use_central_api = true;
            }

            if ($order_resp->OrderStatus->MajorStatus == 'Initial') {

                $contact_number = '';
                //get client data to prefiiled data
                Loader::loadModels($this, array("Clients"));
                $client_data = $this->Clients->get($service->client_id, $get_settings = false);

                //get client contact no
                if ($client_data->contact_id && $client_data->contact_id > 0) {
                    Loader::loadModels($this, array("Contacts"));
                    $client_contact_data = $this->Contacts->getNumbers($client_data->contact_id);
                    if (!empty($client_contact_data)) {
                        $contact_number = $client_contact_data[0]->number;
                    }
                }

                //Pre-filled data
                $admin_first_name = isset($post['thesslstore_admin_first_name']) ? $post['thesslstore_admin_first_name'] : $client_data->first_name;
                $admin_last_name = isset($post['thesslstore_admin_last_name']) ? $post['thesslstore_admin_last_name'] : $client_data->last_name;
                $admin_title = isset($post['thesslstore_admin_title']) ? $post['thesslstore_admin_title'] : '';
                $admin_email = isset($post['thesslstore_admin_email']) ? $post['thesslstore_admin_email'] : $client_data->email;
                $admin_phone = isset($post['thesslstore_admin_phone']) ? $post['thesslstore_admin_phone'] : $contact_number;

                $admin_org_name = isset($post['thesslstore_org_name']) ? $post['thesslstore_org_name'] : $client_data->company;
                $admin_org_division = isset($post['thesslstore_org_division']) ? $post['thesslstore_org_division'] : '';
                $admin_address1 = isset($post['thesslstore_admin_address1']) ? $post['thesslstore_admin_address1'] : $client_data->address1;
                $admin_address2 = isset($post['thesslstore_admin_address2']) ? $post['thesslstore_admin_address2'] : $client_data->address2;
                $admin_city = isset($post['thesslstore_admin_city']) ? $post['thesslstore_admin_city'] : $client_data->city;
                $admin_state = isset($post['thesslstore_admin_state']) ? $post['thesslstore_admin_state'] : $client_data->state;
                $admin_country = isset($post['thesslstore_admin_country']) ? $post['thesslstore_admin_country'] : $client_data->country;
                $admin_zip_code = isset($post['thesslstore_admin_zip']) ? $post['thesslstore_admin_zip'] : $client_data->zip;

                //Retrieve the meta value for 'use_default_tech_details'
                $useDefaultTechDetails = 'no';
                $moduleID = $package->module_id;
                $meta = $this->ModuleManager->getMeta($moduleID);
                if(isset($meta->use_default_tech_details))
                {
                    $useDefaultTechDetails = $meta->use_default_tech_details;
                }

                if($useDefaultTechDetails == 'yes'){
                    $post['thesslstore_tech_first_name'] = $meta->thesslstore_tech_first_name;
                    $post['thesslstore_tech_last_name'] = $meta->thesslstore_tech_last_name;
                    $post['thesslstore_tech_title'] = $meta->thesslstore_tech_title;
                    $post['thesslstore_tech_email'] = $meta->thesslstore_tech_email;
                    $post['thesslstore_tech_phone'] = $meta->thesslstore_tech_phone;
                }

                $tech_first_name = isset($post['thesslstore_tech_first_name']) ? $post['thesslstore_tech_first_name'] : $client_data->first_name;
                $tech_last_name = isset($post['thesslstore_tech_last_name']) ? $post['thesslstore_tech_last_name'] : $client_data->last_name;
                $tech_title = isset($post['thesslstore_tech_title']) ? $post['thesslstore_tech_title'] : '';
                $tech_email = isset($post['thesslstore_tech_email']) ? $post['thesslstore_tech_email'] : $client_data->email;
                $tech_phone = isset($post['thesslstore_tech_phone']) ? $post['thesslstore_tech_phone'] : $contact_number;

                $additional_san_text_value = '';
                if(isset($post['thesslstore_additional_san'])){
                    if(is_array($post['thesslstore_additional_san'])){
                        $additional_san_text_value = implode("\n", $post['thesslstore_additional_san']);
                    }
                    else{
                        $additional_san_text_value = $post['thesslstore_additional_san'];
                    }
                }
                $step = 1;
                $posted_from_step = 0;
                $posted = false;
                if (isset($post['thesslstore_gen_step'])) {
                    $step = $post['thesslstore_gen_step'];
                    $posted = true;
                    $posted_from_step = $post['thesslstore_gen_step'];
                }

                // Get the service fields
                $san_count = $order_resp->SANCount;
                $auth_domains = array();
                $additional_sans = array();

                //Authentication Method
                $auth_methods = array();
                $auth_methods['HTTP'] = 'HTTP File';
                if ($vendor_name == 'COMODO' || $vendor_name == 'SECTIGO')
                    $auth_methods['HTTPS'] = 'HTTPS File';
                $auth_methods['DNS'] = 'DNS';
                if ($vendor_name != 'COMODO' && $vendor_name != 'SECTIGO')
                    $auth_methods['EMAIL'] = 'E-Mail';

                //Signature algorithm and get existing organisation list
                $organisation_list = array();
                if($vendor_name == 'DIGICERT'){
                    $signature_algorithms = array('sha256' => 'SHA-256', 'sha384' => 'SHA-384', 'sha512' => 'SHA-512');
                    $organisation_list = $this->getOrganizationList($service);
                }
                else{
                    $signature_algorithms = array('SHA2-256' => 'SHA-2', 'SHA1' => 'SHA-1');
                }


                if ($step == 1) {
                    $this->view = new View("tab_client_generate_cert_step1", "default");
                    $this->view->base_uri = $this->base_uri;
                    $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

                    // Load the helpers required for this view
                    Loader::loadHelpers($this, array("Form", "Html"));

                    // Validate the service-specific fields
                    if ($posted) {
                        $need_to_create_org = false;
                        if($vendor_name == 'DIGICERT'){
                            if((isset($post['ssl_org_detail']) && $post['ssl_org_detail'] == 'new') || empty($post['thesslstore_org_id'])){
                                $need_to_create_org = true;
                            }
                        }

                        $this->validateGenerateCertStep1($package, $post, $san_count, $need_to_create_org);

                        //create organisation
                        if (!$this->Input->errors()){
                            if($need_to_create_org){
                                $org_id = $this->createOrganization($service, $package, $post);
                                if($org_id){
                                    $post['thesslstore_org_id'] = $org_id;
                                }
                            }
                        }

                        if(!$this->Input->errors()){
                            $step = 2;

                            //get main domain name from the CSR
                            $validate_csr_resp = $this->validateCSR($post['thesslstore_csr'], $product_code);
                            $common_name = $validate_csr_resp->DomainName;
                            $auth_domains[$common_name] = $common_name;
                            if(isset($post['thesslstore_additional_san'])){
                                $additional_sans = explode("\n", $post['thesslstore_additional_san']);
                                $additional_sans = array_map('trim', $additional_sans);
                                $additional_sans = array_filter($additional_sans);
                            }

                            /*
                             * quicksslpremiummd has additional san but it allows only subdomain of main domain
                             * as additional san and allows only main domain's approval email.
                             */
                            if ($vendor_name == 'COMODO' || $vendor_name == 'SECTIGO' || $vendor_name == 'DIGICERT'){
                                foreach($additional_sans as $san){
                                    $auth_domains[$san] = $san;
                                }
                            }

                        }
                    }
                    if ($step == 1) {
                        //Retrieve the value of 'use_default_tech_details' meta
                        $useDefaultTechDetails = 'no';
                        $moduleID = $package->module_id;
                        $meta = $this->ModuleManager->getMeta($moduleID,'use_default_tech_details');
                        if(isset($meta->use_default_tech_details))
                        {
                            $useDefaultTechDetails = $meta->use_default_tech_details;
                        }
                        $this->view->set("use_default_tech_details", $useDefaultTechDetails);
                        $this->view->set("thesslstore_additional_san", $additional_san_text_value);
                        $this->view->set("thesslstore_webserver_types", $this->getWebserverTypes($vendor_name));
                        $this->view->set("thesslstore_countries", $this->getCountryList());
                        $this->view->set("thesslstore_signature_algorithms", $signature_algorithms);

                        $this->view->set("vars", (object)$post);

                        //admin contact
                        $this->view->set("thesslstore_admin_first_name", $admin_first_name);
                        $this->view->set("thesslstore_admin_last_name", $admin_last_name);
                        $this->view->set("thesslstore_admin_title", $admin_title);
                        $this->view->set("thesslstore_admin_email", $admin_email);
                        $this->view->set("thesslstore_admin_phone", $admin_phone);
                        $this->view->set("thesslstore_org_name", $admin_org_name);
                        $this->view->set("thesslstore_org_division", $admin_org_division);
                        $this->view->set("thesslstore_admin_address1", $admin_address1);
                        $this->view->set("thesslstore_admin_address2", $admin_address2);
                        $this->view->set("thesslstore_admin_city", $admin_city);
                        $this->view->set("thesslstore_admin_state", $admin_state);
                        $this->view->set("thesslstore_admin_country", $admin_country);
                        $this->view->set("thesslstore_admin_zip", $admin_zip_code);

                        //Technical Contact
                        $this->view->set("thesslstore_tech_first_name", $tech_first_name);
                        $this->view->set("thesslstore_tech_last_name", $tech_last_name);
                        $this->view->set("thesslstore_tech_title", $tech_title);
                        $this->view->set("thesslstore_tech_email", $tech_email);
                        $this->view->set("thesslstore_tech_phone", $tech_phone);

                        $this->view->set("service", $service);
                        $this->view->set("san_count", $san_count);
                        $this->view->set("vendor_name", $vendor_name);
                        $this->view->set("validation_type", $validation_type);
                        $this->view->set("organisation_list", $organisation_list);
                        $this->view->set("service_id", $service->id);
                        $this->view->set("use_central_api", $use_central_api);
                        $this->view->set("cert_generation_link", $cert_generation_link);
                        $this->view->set("step", 1);

                        return $this->view->fetch();
                    }

                }
                //Successfully passed step 1
                if ($step == 2){
                    if ($posted_from_step == 1){

                        $alias_emails = array(
                            '' => 'none',
                            'admin@' => 'admin@',
                            'administrator@' => 'administrator@',
                            'hostmaster@' => 'hostmaster@',
                            'postmaster@' => 'postmaster@',
                            'webmaster@' => 'webmaster@',
                        );

                        $this->view = new View("tab_client_generate_cert_step2", "default");
                        $this->view->base_uri = $this->base_uri;
                        $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

                        Loader::loadHelpers($this, array("Form", "Html"));

                        $this->view->set("vars", (object)$post);
                        $this->view->set("step", 2);
                        $this->view->set("thesslstore_auth_methods", $auth_methods);
                        $this->view->set("additional_sans", $additional_sans);
                        $this->view->set("auth_domains", $auth_domains);
                        $this->view->set("sslstore_alias_emails", $alias_emails);
                        $this->view->set("service_id", $service->id);
                        $this->view->set("vendor_name", $vendor_name);
                        $this->view->set("product_code", $order_resp->ProductCode);
                        $this->view->set("get_approver_email_url", $this->base_uri . "services/manage/" .$service->id. "/tabClientResendApproverEmail/");
                        //create alias emails for domain
                        foreach($auth_domains as $dm){
                            //$base_domain = $this->getPrimaryDomain($dm);
                            $dm = str_replace("*.","", $dm);
                            $dm = str_replace("www.","", $dm);
                            $auth_domain_alias_emails[$dm]['admin@'] = 'admin@'.$dm;
                            $auth_domain_alias_emails[$dm]['administrator@'] = 'administrator@'.$dm;
                            $auth_domain_alias_emails[$dm]['hostmaster@'] = 'hostmaster@'.$dm;
                            $auth_domain_alias_emails[$dm]['postmaster@'] = 'postmaster@'.$dm;
                            $auth_domain_alias_emails[$dm]['webmaster@'] = 'webmaster@'.$dm;
                        }
                        $this->view->set("auth_domain_alias_emails", $auth_domain_alias_emails);
                        $this->view->set("is_symantec_order", ($order_resp->VendorName == 'GEOTRUST' || $order_resp->VendorName == 'THAWTE' || $order_resp->VendorName == 'SYMANTEC' || $order_resp->VendorName == 'RAPIDSSL') ? 'yes' : 'no');

                        return $this->view->fetch();
                    }
                    elseif ($posted_from_step == 2){
                        $success = $this->placeFullOrder($package, $service, $post, $order_resp);
                        sleep(3);
                        if($success){
                            $step = 3;
                        }
                        else{
                            $step = 1;
                        }
                    }

                    if ($step == 1) {
                        //Display error with step 1
                        $this->view = new View("tab_client_generate_cert_step1", "default");
                        $this->view->base_uri = $this->base_uri;
                        $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

                        Loader::loadHelpers($this, array("Form", "Html"));

                        $this->view->set("thesslstore_additional_san", $additional_san_text_value);
                        $this->view->set("thesslstore_webserver_types", $this->getWebserverTypes($vendor_name));
                        $this->view->set("thesslstore_countries", $this->getCountryList());
                        $this->view->set("thesslstore_signature_algorithms", $signature_algorithms);
                        $this->view->set("thesslstore_auth_methods", $auth_methods);
                        $this->view->set("vars", (object)$post);

                        //admin contact
                        $this->view->set("thesslstore_admin_first_name", $admin_first_name);
                        $this->view->set("thesslstore_admin_last_name", $admin_last_name);
                        $this->view->set("thesslstore_admin_title", $admin_title);
                        $this->view->set("thesslstore_admin_email", $admin_email);
                        $this->view->set("thesslstore_admin_phone", $admin_phone);
                        $this->view->set("thesslstore_org_name", $admin_org_name);
                        $this->view->set("thesslstore_org_division", $admin_org_division);
                        $this->view->set("thesslstore_admin_address1", $admin_address1);
                        $this->view->set("thesslstore_admin_address2", $admin_address2);
                        $this->view->set("thesslstore_admin_city", $admin_city);
                        $this->view->set("thesslstore_admin_state", $admin_state);
                        $this->view->set("thesslstore_admin_country", $admin_country);
                        $this->view->set("thesslstore_admin_zip", $admin_zip_code);

                        //Technical Contact
                        $this->view->set("thesslstore_tech_first_name", $tech_first_name);
                        $this->view->set("thesslstore_tech_last_name", $tech_last_name);
                        $this->view->set("thesslstore_tech_title", $tech_title);
                        $this->view->set("thesslstore_tech_email", $tech_email);
                        $this->view->set("thesslstore_tech_phone", $tech_phone);


                        $this->view->set("service", $service);
                        $this->view->set("san_count", $san_count);
                        $this->view->set("vendor_name", $vendor_name);
                        $this->view->set("validation_type", $validation_type);
                        $this->view->set("organisation_list", $organisation_list);
                        $this->view->set("service_id", $service->id);
                        $this->view->set("step", 1);
                        $this->view->set("use_central_api", $use_central_api);
                        $this->view->set("cert_generation_link", $cert_generation_link);

                        return $this->view->fetch();
                    }
                }

                if($step == 3){
                    $this->view = new View("tab_client_generate_cert_step3", "default");
                    $this->view->base_uri = $this->base_uri;
                    $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

                    Loader::loadHelpers($this, array("Html"));

                    $this->view->set("vars", (object)$post);

                    //call order status to get auth details
                    $order_status = $this->getSSLOrderStatus($thesslstore_order_id, $package->meta->thesslstore_vendor_name);
                    //get Auth Details from order status
                    $auth_details = $this->getAuthDetails($order_status);
                    $this->view->set("auth_details", $auth_details);
                    return $this->view->fetch();
                }
            }
            else{
                $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.generate_cert_invalid_certificate_status", true))));
                return;
            }
        }
        else {
            $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.invalid_service_status", true))));
            return;
        }
    }

    /**
     * Client Resend Approver Email for Pending order tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientResendApproverEmail($package, $service, array $get=null, array $post=null, array $files=null) {

        if($service->status == 'active'){



            if(isset($_POST['action']) && $_POST['action'] == 'getapproverlist'){
                //This will return approver email list for ajax request called from cert generation second step
                $api = $this->getApi();

                $approver_list = array();
                $is_error = false;
                $error_message = '';

                $order_approver_email_req = new order_approverlist_request();
                $order_approver_email_req->ProductCode = $_POST['product_code'];
                $order_approver_email_req->DomainName = $_POST['domain_name'];

                $order_approver_email_resp = $api->order_approverlist($order_approver_email_req);

                foreach($order_approver_email_resp->ApproverEmailList as $email){
                    if($email != 'support_preprod@geotrust.com' && $email != 'support@geotrust.com' && strpos($email,"@") != false){
                        $approver_list[] = $email;
                    }
                }

                if(empty($approver_list)){
                    $is_error = true;
                    $error_message = 'Emails Not Found';

                    $this->log($this->api_partner_code . "|get-approver-list", serialize($order_approver_email_req), "input", true);
                    $this->log($this->api_partner_code . "|get-approver-list", serialize($order_approver_email_resp), "output", true);
                }

                echo json_encode(array('email_list' => $approver_list, 'is_error' => $is_error, 'error_message' => $error_message));
                exit;
                return;

            }
            elseif(isset($_POST['action']) && $_POST['action'] == 'change_approver'){

                $api = $this->getApi(null,null,'',$IsUsedForTokenSystem = true, $_POST['ssl_token']);

                $is_error = false;
                $message = Language::_("ThesslstoreModule.success.change_approver_email", true);

                if($_POST['dcv_method'] == 'HTTP'){
                    $new_approver_method = ($_POST['vendor_name'] == 'COMODO' || $_POST['vendor_name'] == 'SECTIGO' ? 'HTTP_CSR_HASH' : 'FILE');
                }
                elseif($_POST['dcv_method'] == 'HTTPS'){
                    $new_approver_method = 'HTTPS_CSR_HASH';
                }
                elseif($_POST['dcv_method'] == 'DNS'){
                    $new_approver_method = ($_POST['vendor_name'] == 'COMODO' || $_POST['vendor_name'] == 'SECTIGO' ? 'CNAME_CSR_HASH' : 'DNS');
                }
                else{
                    $new_approver_method = 'EMAIL';
                }

                if($_POST['vendor_name'] == 'COMODO' || $_POST['vendor_name'] == 'SECTIGO'  || $_POST['vendor_name'] == 'DIGICERT' || $_POST['dcv_method'] == 'HTTP' || $_POST['dcv_method'] == 'DNS' || ($new_approver_method == 'EMAIL' && $_POST['current_auth_method'] != 'EMAIL' )){
                    $change_approver_method_req = new order_change_approver_method_request();
                    if($new_approver_method == 'EMAIL' && $_POST['vendor_name'] != 'DIGICERT'){
                        $change_approver_method_req->ResendEmail = $_POST['dcv_method'];
                    }

                    $change_approver_method_req->TheSSLStoreOrderID = 0;
                    $change_approver_method_req->DomainNames = $_POST['domain_name'];
                    $change_approver_method_req->ApproverMethod = $new_approver_method;

                    if($_POST['vendor_name'] == 'DIGICERT'){
                        $change_approver_method_resp = $api->digicert_change_approver_method($change_approver_method_req);
                    }
                    else{
                        $change_approver_method_resp = $api->order_change_approver_method($change_approver_method_req);
                    }

                    if($change_approver_method_resp == NULL || $change_approver_method_resp->isError){
                        $is_error = true;
                        $message = isset($change_approver_method_resp->Message[0]) ? $change_approver_method_resp->Message[0] : Language::_("ThesslstoreModule.!error.api.internal", true);
                        $this->log($this->api_partner_code . "|change-approver-method", serialize($change_approver_method_req), "input", true);
                        $this->log($this->api_partner_code . "|change-approver-method", serialize($change_approver_method_resp), "output", true);
                    }
                }
                else{
                    //Call resend email for change symantec email to email
                    $resend_email_req = new order_resend_request();
                    $resend_email_req->TheSSLStoreOrderID = 0;
                    $resend_email_req->ResendEmailType = 'ApproverEmail';
                    $resend_email_req->ResendEmail = $_POST['dcv_method'];
                    $resend_email_resp = $api->order_resend($resend_email_req);
                    if($resend_email_resp == NULL || $resend_email_resp->isError){
                        $is_error = true;
                        $message = isset($resend_email_resp->Message[0]) ? $resend_email_resp->Message[0] : Language::_("ThesslstoreModule.!error.api.internal", true);
                        $this->log($this->api_partner_code . "|resend-approver-email", serialize($resend_email_req), "input", true);
                        $this->log($this->api_partner_code . "|resend-approver-email", serialize($resend_email_resp), "output", true);
                    }
                }
                echo json_encode(array('message' => str_ireplace("TheSSLStore","Store",$message), 'isError' => $is_error));
                exit;
                return;

            }
            elseif(isset($_POST['action']) && $_POST['action'] == 'resend_approver'){
                $api = $this->getApi(null,null,'',$IsUsedForTokenSystem = true, $_POST['ssl_token']);

                $is_error = false;
                $message = Language::_("ThesslstoreModule.success.resend_approver_email", true);

                $resend_email_req = new order_resend_request();
                $resend_email_req->TheSSLStoreOrderID = 0;
                $resend_email_req->ResendEmailType = 'ApproverEmail';
                $resend_email_req->DomainNames = $_POST['domain_name'];
                if($_POST['vendor_name'] == 'DIGICERT'){
                    $resend_email_resp = $api->digicert_order_resend($resend_email_req);
                }
                else{
                    $resend_email_resp = $api->order_resend($resend_email_req);
                }
                if($resend_email_resp == NULL || $resend_email_resp->isError){
                    $is_error = true;
                    $message = isset($resend_email_resp->Message[0]) ? $resend_email_resp->Message[0] : Language::_("ThesslstoreModule.!error.api.internal", true);
                    $this->log($this->api_partner_code . "|resend-approver-email", serialize($resend_email_req), "input", true);
                    $this->log($this->api_partner_code . "|resend-approver-email", serialize($resend_email_resp), "output", true);
                }

                echo json_encode(array('message' => str_ireplace("TheSSLStore","Store",$message), 'isError' => $is_error));
                exit;
                return;

            }
            else{
                $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.invalid_screen", true))));
                return;
            }
        }
        else {
            $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.invalid_service_status", true))));
            return;
        }

    }
    /**
     * placed full order for certificate generation process
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $post Any POST parameters
     * @param stdClass $order_status_resp A stdClass object
     * @return boolean true|false based on success
     */
    private function placeFullOrder($package,$service,$post,$order_status_resp){
        $vars = (object)$post;
        //$api = $this->getApi();

        $service_fields = $this->serviceFieldsToObject($service->fields);

        $san_count = $order_status_resp->SANCount;
        $server_count = $order_status_resp->ServerCount;
        $validity = $order_status_resp->Validity;
        $token = $order_status_resp->Token;

        $product_code = $package->meta->thesslstore_product_code;
        $vendor_name = $package->meta->thesslstore_vendor_name;


        $csr = isset($vars->thesslstore_csr) ? $vars->thesslstore_csr : '';
        $web_server_type = isset($vars->thesslstore_webserver_type) ? $vars->thesslstore_webserver_type : 'Other';
        $signature_algorithm = isset($vars->thesslstore_signature_algorithm) ? $vars->thesslstore_signature_algorithm : '';
        if($vendor_name == 'COMODO'){
            if($signature_algorithm == 'SHA2-256'){
                $signature_algorithm = 'PREFER_SHA2';
            }
            elseif($signature_algorithm == 'SHA1'){
                $signature_algorithm = 'PREFER_SHA1';
            }
        }

        $admin_first_name = isset($vars->thesslstore_admin_first_name) ? $vars->thesslstore_admin_first_name : '';
        $admin_last_name = isset($vars->thesslstore_admin_last_name) ? $vars->thesslstore_admin_last_name : '';
        $admin_title = isset($vars->thesslstore_admin_title) ? $vars->thesslstore_admin_title : '';
        $admin_email = isset($vars->thesslstore_admin_email) ? $vars->thesslstore_admin_email : '';
        $admin_phone = isset($vars->thesslstore_admin_phone) ? $vars->thesslstore_admin_phone : '';
        $org_name = isset($vars->thesslstore_org_name) ? $vars->thesslstore_org_name : '';
        $org_division = isset($vars->thesslstore_org_division) ? $vars->thesslstore_org_division : '';
        $admin_address1 = isset($vars->thesslstore_admin_address1) ? $vars->thesslstore_admin_address1 : '';
        $admin_address2 = isset($vars->thesslstore_admin_address2) ? $vars->thesslstore_admin_address2 : '';
        $admin_city = isset($vars->thesslstore_admin_city) ? $vars->thesslstore_admin_city : '';
        $admin_state = isset($vars->thesslstore_admin_state) ? $vars->thesslstore_admin_state : '';
        $admin_country = isset($vars->thesslstore_admin_country) ? $vars->thesslstore_admin_country : '';
        $admin_zip = isset($vars->thesslstore_admin_zip) ? $vars->thesslstore_admin_zip : '';

        //Retrieve the meta value for 'use_default_tech_details'
        $useDefaultTechDetails = 'no';
        $moduleID = $package->module_id;
        $meta = $this->ModuleManager->getMeta($moduleID);
        if(isset($meta->use_default_tech_details))
        {
            $useDefaultTechDetails = $meta->use_default_tech_details;
        }

        if($useDefaultTechDetails != 'yes'){
            $tech_first_name = isset($vars->thesslstore_tech_first_name) ? $vars->thesslstore_tech_first_name : '';
            $tech_last_name = isset($vars->thesslstore_tech_last_name) ? $vars->thesslstore_tech_last_name : '';
            $tech_title = isset($vars->thesslstore_tech_title) ? $vars->thesslstore_tech_title : '';
            $tech_email = isset($vars->thesslstore_tech_email) ? $vars->thesslstore_tech_email : '';
            $tech_phone = isset($vars->thesslstore_tech_phone) ? $vars->thesslstore_tech_phone : '';
            $tech_org_name = isset($vars->thesslstore_org_name) ? $vars->thesslstore_org_name : '';
            $tech_address1 = isset($vars->thesslstore_admin_address1) ? $vars->thesslstore_admin_address1 : '';
            $tech_address2 = isset($vars->thesslstore_admin_address2) ? $vars->thesslstore_admin_address2 : '';
            $tech_city = isset($vars->thesslstore_admin_city) ? $vars->thesslstore_admin_city : '';
            $tech_state = isset($vars->thesslstore_admin_state) ? $vars->thesslstore_admin_state : '';
            $tech_country = isset($vars->thesslstore_admin_country) ? $vars->thesslstore_admin_country : '';
            $tech_zip = isset($vars->thesslstore_admin_zip) ? $vars->thesslstore_admin_zip : '';
        }
        else{
            $tech_first_name = $meta->thesslstore_tech_first_name;
            $tech_last_name = $meta->thesslstore_tech_last_name;
            $tech_title = $meta->thesslstore_tech_title;
            $tech_email = $meta->thesslstore_tech_email;
            $tech_phone = $meta->thesslstore_tech_phone;
            $tech_org_name = $meta->thesslstore_tech_org_name;
            $tech_address1 = $meta->thesslstore_tech_address;
            $tech_address2 = '';
            $tech_city = $meta->thesslstore_tech_city;
            $tech_state = $meta->thesslstore_tech_state;
            $tech_country = $meta->thesslstore_tech_country;
            $tech_zip = $meta->thesslstore_tech_zipcode;
        }

        $org_id = isset($vars->thesslstore_org_id) ? $vars->thesslstore_org_id : 0;
        //$approver_methods = array();

        $sans = array();
        if(isset($vars->thesslstore_additional_san)){
            foreach($vars->thesslstore_additional_san as $san){
                if(trim($san)!= '')
                    $sans[] = $san;
            }
        }

        //call validate CSR to get domain name
        $validate_csr_resp = $this->validateCSR($csr, $product_code);

        $domain_name = $validate_csr_resp->DomainName;

        $contact = new contact();
        $contact->AddressLine1 = html_entity_decode($admin_address1);
        $contact->AddressLine2 = html_entity_decode($admin_address2);
        $contact->City = html_entity_decode($admin_city);
        $contact->Region = html_entity_decode($admin_state);
        $contact->Country = html_entity_decode($admin_country);
        $contact->Email = html_entity_decode($admin_email);
        $contact->FirstName = html_entity_decode($admin_first_name);
        $contact->LastName = html_entity_decode($admin_last_name);
        $contact->OrganizationName = html_entity_decode($org_name);
        $contact->Phone = html_entity_decode($admin_phone);
        $contact->PostalCode = html_entity_decode($admin_zip);
        $contact->Title = html_entity_decode($admin_title);

        $tech_contact = new contact();
        $tech_contact->AddressLine1 = html_entity_decode($tech_address1);
        $tech_contact->AddressLine2 = html_entity_decode($tech_address2);
        $tech_contact->City = html_entity_decode($tech_city);
        $tech_contact->Region = html_entity_decode($tech_state);
        $tech_contact->Country = html_entity_decode($tech_country);
        $tech_contact->Email = html_entity_decode($tech_email);
        $tech_contact->FirstName = html_entity_decode($tech_first_name);
        $tech_contact->LastName = html_entity_decode($tech_last_name);
        $tech_contact->OrganizationName = html_entity_decode($tech_org_name);
        $tech_contact->Phone = html_entity_decode($tech_phone);
        $tech_contact->PostalCode = html_entity_decode($tech_zip);
        $tech_contact->Title = html_entity_decode($tech_title);

        $new_order = new order_neworder_request();


        $new_order->AddInstallationSupport = false;
        $new_order->AdminContact = $contact;
        $new_order->CSR = $csr;
        $new_order->DomainName = $validate_csr_resp->DomainName;
        $new_order->RelatedTheSSLStoreOrderID = '';
        $new_order->DNSNames = $sans;
        $new_order->EmailLanguageCode = 'EN';
        $new_order->ExtraProductCodes = '';
        $new_order->OrganizationInfo->DUNS = '';
        $new_order->OrganizationInfo->Division = html_entity_decode($org_division);
        $new_order->OrganizationInfo->IncorporatingAgency = '';
        $new_order->OrganizationInfo->JurisdictionCity = $contact->City;
        $new_order->OrganizationInfo->JurisdictionCountry = $contact->Country;
        $new_order->OrganizationInfo->JurisdictionRegion = $contact->Region;
        $new_order->OrganizationInfo->OrganizationName = html_entity_decode($org_name);
        $new_order->OrganizationInfo->RegistrationNumber = '';
        $new_order->OrganizationInfo->OrganizationAddress->AddressLine1 = $contact->AddressLine1;
        $new_order->OrganizationInfo->OrganizationAddress->AddressLine2 = $contact->AddressLine2;
        $new_order->OrganizationInfo->OrganizationAddress->AddressLine3 = '';
        $new_order->OrganizationInfo->OrganizationAddress->City = $contact->City;
        $new_order->OrganizationInfo->OrganizationAddress->Country = $contact->Country;
        $new_order->OrganizationInfo->OrganizationAddress->Fax = $contact->Fax;
        $new_order->OrganizationInfo->OrganizationAddress->LocalityName = '';
        $new_order->OrganizationInfo->OrganizationAddress->Phone = html_entity_decode($admin_phone);
        $new_order->OrganizationInfo->OrganizationAddress->PostalCode = html_entity_decode($admin_zip);
        $new_order->OrganizationInfo->OrganizationAddress->Region = html_entity_decode($admin_state);

        $new_order->PreOrganizationId = $org_id;

        $new_order->ProductCode = $product_code;
        $new_order->ReserveSANCount = $san_count;
        $new_order->ServerCount = $server_count;
        $new_order->SpecialInstructions = '';
        $new_order->TechnicalContact = $tech_contact;
        $new_order->ValidityPeriod = $validity; //number of months
        $new_order->WebServerType = $web_server_type;
        $new_order->isCUOrder = false;
        $new_order->isRenewalOrder = true;
        $new_order->isTrialOrder = false;
        $new_order->SignatureHashAlgorithm = $signature_algorithm;

        //Pass additional days for new order, based on the admin side settings
        $additionalDays = 0;
        $moduleID = $package->module_id;
        $meta = $this->ModuleManager->getMeta($moduleID,'additional_days_for_neworder');
        if(isset($meta->additional_days_for_neworder))
        {
            $additionalDays = $meta->additional_days_for_neworder;
        }
        if($order_status_resp->ProductCode == 'freessl' || $additionalDays <=0){
            $new_order->isRenewalOrder = false;
        }
        else{
            $new_order->isRenewalOrder = true;
            $new_order->RenewalDays = $additionalDays;
        }

        if(strtoupper($order_status_resp->VendorName) != 'DIGICERT'){

            $new_order->FileAuthDVIndicator = false;
            $new_order->HTTPSFileAuthDVIndicator = false;
            $new_order->CNAMEAuthDVIndicator = false;
            $approver_methods = array();
            if(is_array($vars->ssl_auth_methods)){
                if((strtoupper($order_status_resp->VendorName) == 'COMODO' || strtoupper($order_status_resp->VendorName) == 'SECTIGO') && $order_status_resp->SANCount > 0){
                    //For comodo multi-domain product
                    foreach($vars->ssl_auth_methods as $domain => $method){
                        if($method == 'HTTP'){
                            $approver_methods[] = 'HTTP_CSR_HASH';
                        }
                        elseif($method == 'HTTPS'){
                            $approver_methods[] = 'HTTPS_CSR_HASH';
                        }
                        elseif($method == 'DNS'){
                            $approver_methods[] = 'CNAME_CSR_HASH';
                        }
                        else{
                            $approver_methods[] = $method;
                        }
                    }
                }
                else{
                    //For comodo single domain and other vendors
                    foreach($vars->ssl_auth_methods as $domain => $method){

                        if($method == 'HTTP'){
                            $new_order->FileAuthDVIndicator = true;
                            if(isset($vars->ssl_approver_email)){
                                $approver_methods[] = $vars->ssl_approver_email;
                            }
                        }
                        elseif($method == 'HTTPS'){
                            $new_order->HTTPSFileAuthDVIndicator = true;
                            if(isset($vars->ssl_approver_email)){
                                $approver_methods[] = $vars->ssl_approver_email;
                            }
                        }
                        elseif($method == 'DNS'){
                            $new_order->CNAMEAuthDVIndicator = true;
                            if(isset($vars->ssl_approver_email)){
                                $approver_methods[] = $vars->ssl_approver_email;
                            }
                        }
                        elseif($method == 'EMAIL'){
                            $approver_methods[] = $vars->ssl_approver_email;
                        }
                        else{
                            $approver_methods[] = $method;
                        }
                    }
                }
            }
            $new_order->ApproverEmail = implode(',', $approver_methods);
        }



        $api = $this->getApi(null,null,'',$IsUsedForTokenSystem = true,$token);

        $this->log($this->api_partner_code . "|ssl-new-order", json_encode($new_order), "input", true);
        if($vendor_name == 'DIGICERT'){
            $results = $this->parseResponse($api->digicert_new_order($new_order));
        }
        else{
            $results = $this->parseResponse($api->order_neworder($new_order));
        }


        if($results != NULL && $results->AuthResponse->isError == false){

            //call set DCV method for digicert
            if($vendor_name == 'DIGICERT'){
                foreach($vars->ssl_auth_methods as $domain => $method){
                    $method = ($method == 'HTTP' ? 'FILE' : $method);
                    $this->setApproverMethod($domain, $org_id, $order_status_resp->TheSSLStoreOrderID, $method);
                }
            }

            //store service fields
            Loader::loadModels($this, array("Services"));
            //store CSR
            if(isset($service_fields->thesslstore_csr)){
                $this->Services->editField($service->id, array(
                    'key' => "thesslstore_csr",
                    'value' => $csr,
                    'encrypted' => 1
                ));
            }
            else {
                $this->Services->addField($service->id, array(
                    'key' => "thesslstore_csr",
                    'value' => $csr,
                    'encrypted' => 1
                ));
            }
            //store domain name
            if(isset($service_fields->thesslstore_fqdn)){
                $this->Services->editField($service->id, array(
                    'key' => "thesslstore_fqdn",
                    'value' => $domain_name,
                    'encrypted' => 0
                ));
            }
            else {
                $this->Services->addField($service->id, array(
                    'key' => "thesslstore_fqdn",
                    'value' => $domain_name,
                    'encrypted' => 0
                ));
            }

            return true;
        }

        return false;

    }

    /**
     * Client Reissue Certificate for Active order tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientReissueCert($package, $service, array $get=null, array $post=null, array $files=null){
        if($service->status == 'active'){
            $api = $this->getApi();

            // Get the service fields
            $service_fields = $this->serviceFieldsToObject($service->fields);

            $order_resp = $this->getSSLOrderStatus($service_fields->thesslstore_order_id, $package->meta->thesslstore_vendor_name);
            if($order_resp != NULL && $order_resp->AuthResponse->isError == false){
                if(strtoupper($order_resp->OrderStatus->MajorStatus) == 'ACTIVE' && strtoupper($order_resp->OrderStatus->MinorStatus) != 'PENDING_REISSUE'){

                    $this->view = new View("tab_client_reissue_cert", "default");
                    $this->view->base_uri = $this->base_uri;
                    $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

                    // Load the helpers required for this view
                    Loader::loadHelpers($this, array("Form", "Html"));

                    $use_central_api = false;
                    $vendor_name = $package->meta->thesslstore_vendor_name;
                    $is_code_signing = $package->meta->thesslstore_is_code_signing;
                    $is_scan_product = $package->meta->thesslstore_is_scan_product;
                    $product_code = $package->meta->thesslstore_product_code;


                    $step = isset($post['thesslstore_reissue_step']) ? $post['thesslstore_reissue_step'] : 1;

                    if($vendor_name == 'CERTUM' || $is_code_signing == 'y' || $is_scan_product == 'y'){
                        //Generate central api link for CERTUM and Code signing products
                        $use_central_api = true;

                        $order_reissue_req = new order_reissue_request();
                        $order_reissue_req->TheSSLStoreOrderID = $service_fields->thesslstore_order_id;
                        $order_reissue_req->WebServerType = $order_resp->WebServerType;
                        $order_reissue_req->PreferEnrollmentLink = true;
                        $order_reissue_req->isWildCard = false;

                        $this->log($this->api_partner_code . "|ssl-reissue-central-api", serialize($order_reissue_req), "input", true);
                        $results = $this->parseResponse($api->order_reissue($order_reissue_req));

                        if ($results != NULL && $results->AuthResponse->isError == false) {
                            $central_api_link = $results->TinyOrderLink;

                            $this->view->set("use_central_api", $use_central_api);
                            $this->view->set("central_api_link", $central_api_link);
                            return $this->view->fetch();
                        }


                    }
                    else{
                        $thesslstore_csr = (isset($service_fields->thesslstore_csr) ? $service_fields->thesslstore_csr : '');
                        $san_count = $order_resp->SANCount;
                        $additional_san_text_value = str_replace(",","\n", $order_resp->DNSNames);
                        if(isset($post['thesslstore_additional_san'])){
                            if(is_array($post['thesslstore_additional_san'])){
                                //$additional_san_text_value = implode("\n", $post['thesslstore_additional_san']);
                            }
                            else{
                                $additional_san_text_value = $post['thesslstore_additional_san'];
                            }
                        }
                        $thesslstore_webserver_type = $order_resp->WebServerType;
                        $thesslstore_signature_algorithm = $order_resp->SignatureHashAlgorithm;

                        if($vendor_name == 'COMODO'){
                            if($thesslstore_signature_algorithm == 'PREFER_SHA2'){
                                $thesslstore_signature_algorithm = 'SHA2-256';
                            }elseif ($thesslstore_signature_algorithm == 'PREFER_SHA1'){
                                $thesslstore_signature_algorithm == 'SHA1';
                            }
                        }


                        if(isset($post['thesslstore_reissue_submit'])){

                            $thesslstore_csr = $post['thesslstore_csr'];
                            $thesslstore_webserver_type = $post['thesslstore_webserver_type'];
                            $thesslstore_signature_algorithm = $post['thesslstore_signature_algorithm'];
                            $this->validateReissueCert($package, $post, $san_count);
                            if (!$this->Input->errors()) {

                                if($step == 1){
                                    $auth_domains = array();
                                    $additional_sans = array();

                                    //Authentication Method
                                    $auth_methods = array();
                                    $auth_methods['HTTP'] = 'HTTP File';
                                    if ($vendor_name == 'COMODO' || $vendor_name == 'SECTIGO')
                                        $auth_methods['HTTPS'] = 'HTTPS File';
                                    $auth_methods['DNS'] = 'DNS';
                                    if ($vendor_name != 'COMODO' && $vendor_name != 'SECTIGO')
                                        $auth_methods['EMAIL'] = 'E-Mail';

                                    $alias_emails = array(
                                        '' => 'none',
                                        'admin@' => 'admin@',
                                        'administrator@' => 'administrator@',
                                        'hostmaster@' => 'hostmaster@',
                                        'postmaster@' => 'postmaster@',
                                        'webmaster@' => 'webmaster@',
                                    );

                                    //get main domain name from the CSR
                                    $validate_csr_resp = $this->validateCSR($post['thesslstore_csr'], $product_code);
                                    $common_name = $validate_csr_resp->DomainName;
                                    $auth_domains[$common_name] = $common_name;
                                    if(isset($post['thesslstore_additional_san'])){
                                        $additional_sans = explode("\n", $post['thesslstore_additional_san']);
                                        $additional_sans = array_map('trim', $additional_sans);
                                        $additional_sans = array_filter($additional_sans);
                                    }

                                    if ($vendor_name == 'COMODO' || $vendor_name == 'SECTIGO' || $vendor_name == 'DIGICERT'){
                                        foreach($additional_sans as $san){
                                            $auth_domains[$san] = $san;
                                        }
                                    }

                                    //create alias emails for domain
                                    foreach($auth_domains as $dm){
                                        //$base_domain = $this->getPrimaryDomain($dm);
                                        $dm = str_replace("*.","", $dm);
                                        $dm = str_replace("www.","", $dm);
                                        $auth_domain_alias_emails[$dm]['admin@'] = 'admin@'.$dm;
                                        $auth_domain_alias_emails[$dm]['administrator@'] = 'administrator@'.$dm;
                                        $auth_domain_alias_emails[$dm]['hostmaster@'] = 'hostmaster@'.$dm;
                                        $auth_domain_alias_emails[$dm]['postmaster@'] = 'postmaster@'.$dm;
                                        $auth_domain_alias_emails[$dm]['webmaster@'] = 'webmaster@'.$dm;
                                    }



                                    $this->view->set("thesslstore_auth_methods", $auth_methods);
                                    $this->view->set("additional_sans", $additional_sans);
                                    $this->view->set("auth_domains", $auth_domains);
                                    $this->view->set("sslstore_alias_emails", $alias_emails);
                                    $this->view->set("auth_domain_alias_emails", $auth_domain_alias_emails);
                                    $this->view->set("product_code", $product_code);
                                    $this->view->set("get_approver_email_url", $this->base_uri . "services/manage/" .$service->id. "/tabClientResendApproverEmail/");
                                    $step = 2;
                                }
                                elseif($step == 2){
                                    $success = $this->reIssueCertificate($package, $service, $post, $order_resp);
                                    //if any error then diplay step 1 with error
                                    if(!$success){
                                        $step = 1;
                                    }
                                    else{
                                        $step = 3;
                                        //call order status to get auth details
                                        $new_order_status = $this->getSSLOrderStatus($service_fields->thesslstore_order_id, $package->meta->thesslstore_vendor_name);
                                        //get Auth Details from order status
                                        $auth_details = $this->getAuthDetails($new_order_status);
                                        $this->view->set("auth_details", $auth_details);
                                    }
                                }
                            }
                            else{
                                //When any error display step1
                                $step = 1;
                            }
                        }
                        if($vendor_name == 'DIGICERT'){
                            $signature_algorithms = array('sha256' => 'SHA-256', 'sha384' => 'SHA-384', 'sha512' => 'SHA-512');
                        }
                        else{
                            $signature_algorithms = array('SHA2-256' => 'SHA-2', 'SHA1' => 'SHA-1');
                        }
                        $this->view->set("service_id", $service->id);
                        $this->view->set("step", $step);
                        $this->view->set("thesslstore_csr", $thesslstore_csr);
                        $this->view->set("thesslstore_webserver_types", $this->getWebserverTypes($vendor_name));
                        $this->view->set("thesslstore_webserver_type", $thesslstore_webserver_type);
                        $this->view->set("thesslstore_signature_algorithms",$signature_algorithms);
                        $this->view->set("thesslstore_signature_algorithm", $thesslstore_signature_algorithm);
                        $this->view->set("san_count", $san_count);
                        $this->view->set("thesslstore_additional_san", $additional_san_text_value);
                        $this->view->set("vendor_name", $vendor_name);
                        $this->view->set("use_central_api", $use_central_api);


                        return $this->view->fetch();
                    }
                }
                else {
                    $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.reissue_cert_invalid_certificate_status", true))));
                    return;
                }
            }

        }
        else {
            $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.invalid_service_status", true))));
            return;
        }
    }

    /**
     * Re-Issue Certificate Call
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $post Any POST parameters
     * @param stdClass $order_status_resp A stdClass object
     * @return boolean true|false based on success
     */
    private function reIssueCertificate($package, $service, $post, $order_status_resp){
        $vars = (object)$post;
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $api = $this->getApi();
        $vendor_name = $package->meta->thesslstore_vendor_name;
        $signature_algorithm = $vars->thesslstore_signature_algorithm;
        $additional_san = isset($vars->thesslstore_additional_san) ? $vars->thesslstore_additional_san : array();
        $dns_names = explode(',', $order_status_resp->DNSNames);
        $add_san_old_new_pair = array();
        $delete_san_old_new_pair = array();
        $csr = $vars->thesslstore_csr;

        //New Added SAN
        $added_san = array_diff($additional_san,$dns_names);
        foreach($added_san as $san){
            $pair = new oldNewPair();
            $pair->OldValue = '';
            $pair->NewValue = $san;
            $add_san_old_new_pair[] = $pair;
        }


        //Deleted SAN
        $deleted_san = array_diff($dns_names,$additional_san);
        foreach($deleted_san as $san){
            $pair = new oldNewPair();
            $pair->OldValue = $san;
            $pair->NewValue = '';
            $delete_san_old_new_pair[] = $pair;
        }

        if($vendor_name == 'COMODO'){
            if($signature_algorithm == 'SHA2-256'){
                $signature_algorithm = 'PREFER_SHA2';
            }
            elseif($signature_algorithm == 'SHA1'){
                $signature_algorithm = 'PREFER_SHA1';
            }
        }

        $order_reissue_req = new order_reissue_request();
        $order_reissue_req->CSR = $csr;
        $order_reissue_req->TheSSLStoreOrderID = $order_status_resp->TheSSLStoreOrderID;
        $order_reissue_req->WebServerType = $vars->thesslstore_webserver_type;
        $order_reissue_req->isWildCard = false;
        $order_reissue_req->PreferEnrollmentLink = false;

        if(strtoupper($vendor_name) != 'DIGICERT'){
            $order_reissue_req->FileAuthDVIndicator = false;
            $order_reissue_req->HTTPSFileAuthDVIndicator = false;
            $order_reissue_req->CNAMEAuthDVIndicator = false;
            $approver_methods = array();
            if(is_array($vars->ssl_auth_methods)){
                if((strtoupper($order_status_resp->VendorName) == 'COMODO' || strtoupper($order_status_resp->VendorName) == 'SECTIGO') && $order_status_resp->SANCount > 0){
                    //For comodo multi-domain product
                    foreach($vars->ssl_auth_methods as $domain => $method){
                        if($method == 'HTTP'){
                            $approver_methods[] = 'HTTP_CSR_HASH';
                        }
                        elseif($method == 'HTTPS'){
                            $approver_methods[] = 'HTTPS_CSR_HASH';
                        }
                        elseif($method == 'DNS'){
                            $approver_methods[] = 'CNAME_CSR_HASH';
                        }
                        else{
                            $approver_methods[] = $method;
                        }
                    }
                }
                else{
                    //For comodo single domain and other vendors
                    foreach($vars->ssl_auth_methods as $domain => $method){

                        if($method == 'HTTP'){
                            $order_reissue_req->FileAuthDVIndicator = true;
                            if(isset($vars->ssl_approver_email)){
                                $approver_methods[] = $vars->ssl_approver_email;
                            }
                        }
                        elseif($method == 'HTTPS'){
                            $order_reissue_req->HTTPSFileAuthDVIndicator = true;
                            if(isset($vars->ssl_approver_email)){
                                $approver_methods[] = $vars->ssl_approver_email;
                            }
                        }
                        elseif($method == 'DNS'){
                            $order_reissue_req->CNAMEAuthDVIndicator = true;
                            if(isset($vars->ssl_approver_email)){
                                $approver_methods[] = $vars->ssl_approver_email;
                            }
                        }
                        elseif($method == 'EMAIL'){
                            $approver_methods[] = $vars->ssl_approver_email;
                        }
                        else{
                            $approver_methods[] = $method;
                        }
                    }
                }
            }
            $order_reissue_req->ApproverEmail = implode(',', $approver_methods);
        }



        $order_reissue_req->SignatureHashAlgorithm = $signature_algorithm;
        $order_reissue_req->AddSAN = $add_san_old_new_pair;
        $order_reissue_req->DeleteSAN = $delete_san_old_new_pair;
        $order_reissue_req->ReissueEmail = $order_status_resp->AdminContact->Email;
        if($vendor_name == 'COMODO'){
            $order_reissue_req->CSRUniqueValue = date('YmdHisa');
        }

        $this->log($this->api_partner_code . "|ssl-reissue", serialize($order_reissue_req), "input", true);
        if($vendor_name == 'DIGICERT'){
            $results = $this->parseResponse($api->digicert_order_reissue($order_reissue_req));
        }
        else{
            $results = $this->parseResponse($api->order_reissue($order_reissue_req));
        }

        //Update CSR
        if($results != NULL && $results->AuthResponse->isError == false){
            sleep(3);
            //call set DCV method for digicert
            if($vendor_name == 'DIGICERT'){
                foreach($vars->ssl_auth_methods as $domain => $method){
                    $method = ($method == 'HTTP' ? 'FILE' : $method);
                    $this->setApproverMethod($domain, $order_status_resp->PreOrganizationId, $order_status_resp->TheSSLStoreOrderID, $method);
                }
            }


            //store service fields
            Loader::loadModels($this, array("Services"));
            //store CSR
            if(isset($service_fields->thesslstore_csr)) {
                $this->Services->editField($service->id, array(
                    'key' => "thesslstore_csr",
                    'value' => $csr,
                    'encrypted' => 1
                ));
            }else{
                $this->Services->addField($service->id, array(
                    'key' => "thesslstore_csr",
                    'value' => $csr,
                    'encrypted' => 1
                ));
            }

            return true;
        }
        return false;

    }

    /**
     * Retrieves a SSL Order Status
     *
     * @param string $order_id TheSSLStore Order ID
     * @return stdClass $response A response of order request
     */
    private function getSSLOrderStatus($order_id, $vendor_name = ''){
        $api = $this->getApi();

        $order_status_req = new order_status_request();
        $order_status_req->TheSSLStoreOrderID = $order_id;

        if(strtoupper($vendor_name) == 'DIGICERT'){
            $response = $api->digicert_order_status($order_status_req);
        }
        else{
            $response = $api->order_status($order_status_req);
        }

        $this->log($this->api_partner_code . "|ssl-order-status", serialize($order_status_req), "input", true);
        $results = $this->parseResponse($response);

        return $results;
    }
    /**
     * validate CSR
     *
     * @param string $csr
     * @param string $product_code SSLStore Product code
     * @param boolean $valid Is function is used for validation in Certificate generation process
     * @return stdClass $response A response of order request
     */
    public function validateCSR($csr, $product_code, $valid = false){
        $api = $this->getApi();
        $csr_req = new csr_request();
        $csr_req->CSR = $csr;
        $csr_req->ProductCode = $product_code;


        $this->log($this->api_partner_code . "|validate-csr", serialize($csr_req), "input", true);
        $results = $this->parseResponse($api->csr($csr_req));

        if($valid){
            if($results == NULL) {
                return false;
            }
            return true;
        }
        return $results;
    }

    /**
     * Return formatted date as per blesta datetime configuration.
     *
     *@param string $utc_date Date in utc.
     *@return string $date A date as per blesta configurations
     */
    private function getFormattedDate($utc_date){

        // Load the Loader to fetch info of All the installed Module
        Loader::loadModels($this, array("Companies"));
        $date_format = $this->Companies->getSetting(Configure::get("Blesta.company_id"), "date_format")->value;
        $timezone = $this->Companies->getSetting(Configure::get("Blesta.company_id"), "timezone")->value;

        if(!empty($date_format) && !empty($timezone))
        {
            $date =  date_create($utc_date, new DateTimeZone("UTC"))
                ->setTimezone(new DateTimeZone($timezone))->format($date_format);

            if($date !== false)
                return $date;
        }
        return $utc_date;
    }

    /**
     * Create Custom Order ID
     *
     * @param string $$serviceID
     * @return $tssCustomOrderID
     */
    private function customOrderId($serviceID){
        //get Blesta order number
        try{
            $blestaOrdersData = $this->Record->select(['orders.order_number','orders.id'])
                ->from('orders')
                ->innerJoin('order_services', 'order_services.order_id', '=', 'orders.id', false)
                ->where('order_services.service_id', '=', $serviceID)->fetch();
        }
        catch(Exception $e){
            $this->log('Database operation: Make Entry in ssl_orders', $e->getMessage(),'output', false);
        }
        $tssCustomOrderID = $blestaOrdersData->order_number;
        //check this order has multiple service if yes then append service id
        $noOfService = $this->Record->select()
            ->from('order_services')
            ->where('order_id', '=', $blestaOrdersData->id)->numResults();
        if($noOfService > 1){
            $tssCustomOrderID = $tssCustomOrderID."-".$serviceID;
        }

        return $tssCustomOrderID;
    }

    /**
     * Management Action tab generic function for Admin
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return array The array of the approver email list
     */
    public function tabAdminManagementAction($package, $service, array $get=null, array $post=null, array $files=null) {
        if($service->status == 'active')
        {
            $this->view = new View("tab_admin_management_action", "default");

            $this->view->base_uri = $this->base_uri;
            $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);

            // Load the helpers required for this view
            Loader::loadHelpers($this, array("Form", "Html", "Widget"));
            // Get the service fields
            $service_fields = $this->serviceFieldsToObject($service->fields);
            $renewFrom = (isset($service_fields->thesslstore_renew_from) ? $service_fields->thesslstore_renew_from : '' );
            // Gether order info using the order status request
            $order_resp = $this->getSSLOrderStatus($service_fields->thesslstore_order_id, $package->meta->thesslstore_vendor_name);

            $this->view->set("serviceID", $service->id);
            $this->view->set("clientID", $service->client_id);
            $this->view->set("orderMajorStatus", $order_resp->OrderStatus->MajorStatus);
            $this->view->set("storeOrderId", $order_resp->TheSSLStoreOrderID);
            $this->view->set("token", $order_resp->Token);
            $this->view->set("renewFrom", $renewFrom);
            return $this->view->fetch();
        }
        else
        {
            return '
                <section class="error_section">
                    <article class="error_box error">
                        <p style="padding:0 0 0 37px">'.Language::_("ThesslstoreModule.!error.invalid_service_status", true).'</p>
                    </article>
                </section>';
        }
    }

    /**
     * Change Approver email tab generic function
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return array The array of the approver email list
     */
    public function tabClientChangeApproverEmail($package, $service, array $get=null, array $post=null, array $files=null)
    {
        if($service->status == 'active'){
            // Get the service fields
            $service_fields = $this->serviceFieldsToObject($service->fields);

            $order_resp = $this->getSSLOrderStatus($service_fields->thesslstore_order_id, $package->meta->thesslstore_vendor_name);

            if(strtoupper($order_resp->OrderStatus->MajorStatus) == 'PENDING' || strtoupper($order_resp->OrderStatus->MinorStatus) == 'PENDING_REISSUE'){
                $this->view = new View("tab_client_change_approver_email", "default");
                $this->view->base_uri = $this->base_uri;
                $this->view->setDefaultView("components" . DS . "modules" . DS . "thesslstore_module" . DS);
                // Load the helpers required for this view
                Loader::loadHelpers($this, array("Form", "Html", "Widget"));

                //Authentication Method
                $auth_methods = array();
                $auth_methods['HTTP'] = 'HTTP File';
                if(strtoupper($order_resp->VendorName) == 'COMODO' || strtoupper($order_resp->VendorName) == 'SECTIGO'){
                    $auth_methods['HTTPS'] = 'HTTPS File';
                }
                $auth_methods['DNS'] = 'DNS';
                if(strtoupper($order_resp->VendorName) == 'DIGICERT'){
                    $auth_methods['EMAIL'] = 'E-Mail';
                }

                $auth_domains = array();
                $auth_domain_alias_emails = array();
                $base_domains = array();
                $base_domain_alias_emails = array();
                $auth_details = $this->getAuthDetails($order_resp);
                foreach($auth_details as $domain => $auth_detail){
                    if($auth_detail['dcv_status'] != 'Validated'){
                        if($auth_detail['method'] == 'FILE'){
                            $auth_domains[$domain] = 'HTTP';
                            if($auth_detail['is_https']){
                                $auth_domains[$domain] = 'HTTPS';
                            }
                        } elseif($auth_detail['method'] == 'CNAME'){
                            $auth_domains[$domain] = 'DNS';
                        } elseif($auth_detail['method'] == 'EMAIL' && strtoupper($order_resp->VendorName) != 'DIGICERT'){
                            $auth_domains[$domain] = $auth_detail['email'];
                        } else{
                            $auth_domains[$domain] = $auth_detail['method'];
                        }

                        //create alias email for domain
                        $domain = str_replace("*.","", $domain);
                        $domain = str_replace("www.","", $domain);
                        $auth_domain_alias_emails[$domain]['admin@'] = 'admin@' . $domain;
                        $auth_domain_alias_emails[$domain]['administrator@'] = 'administrator@' . $domain;
                        $auth_domain_alias_emails[$domain]['hostmaster@'] = 'hostmaster@' . $domain;
                        $auth_domain_alias_emails[$domain]['postmaster@'] = 'postmaster@' . $domain;
                        $auth_domain_alias_emails[$domain]['webmaster@'] = 'webmaster@' . $domain;

                        $base_domain = $this->getPrimaryDomain($domain);
                        $base_domain = str_replace("*.","", $base_domain);
                        $base_domain = str_replace("www.","", $base_domain);
                        if($base_domain != $domain){
                            $base_domains[$domain] = $base_domain;
                            $base_domain_alias_emails[$base_domain]['admin@'] = 'admin@' . $base_domain;
                            $base_domain_alias_emails[$base_domain]['administrator@'] = 'administrator@' . $base_domain;
                            $base_domain_alias_emails[$base_domain]['hostmaster@'] = 'hostmaster@' . $base_domain;
                            $base_domain_alias_emails[$base_domain]['postmaster@'] = 'postmaster@' . $base_domain;
                            $base_domain_alias_emails[$base_domain]['webmaster@'] = 'webmaster@' . $base_domain;
                        }
                    }
                }
                $this->view->set("service", $service);
                $this->view->set("thesslstore_auth_methods",$auth_methods);
                $this->view->set("auth_domains", $auth_domains);
                $this->view->set("auth_domain_alias_emails", $auth_domain_alias_emails);
                $this->view->set("base_domains", $base_domains);
                $this->view->set("base_domain_alias_emails", $base_domain_alias_emails);
                $this->view->set("product_code", $order_resp->ProductCode);
                $this->view->set("vendor_name", strtoupper($order_resp->VendorName));
                $this->view->set("token", $order_resp->Token);
                $this->view->set("get_approver_email_url", $this->base_uri . "services/manage/" .$service->id. "/tabClientResendApproverEmail/");
                return $this->view->fetch();
            }
            else{
                $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.change_approver_email_not_available_for_order", true))));
                return;
            }
        }
        else{
            $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.invalid_service_status", true))));
            return;
        }
    }

    /**
     * Download AuthFile tab generic function
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return Forcessfully download the auth file.
     */
    public function tabClientDownloadAuthFile($package, $service, array $get=null, array $post=null, array $files=null){
        if($service->status == 'active'){
            if(isset($_POST['file_name']) && isset($_POST['file_content'])){
                ob_end_clean();
                header('Content-type:application/octet-stream');
                header('Content-Disposition:attachment; filename=' . $_POST['file_name']);
                echo $_POST['file_content'];
                exit;
            }
            else{
                $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.invalid_screen", true))));
                return;
                exit;
            }
            return 'success';
        }
        else{
            $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.invalid_service_status", true))));
            return;
        }
    }

    /**
     * Download Certificate tab generic function
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return Forcessfully download the Certificate ZIP file.
     */
    public function tabClientDownloadCertificate($package, $service, array $get=null, array $post=null, array $files=null)
    {
        if($service->status == 'active'){
            // Get the service fields
            $service_fields = $this->serviceFieldsToObject($service->fields);
            $api = $this->getApi();
            $downloadReq = new order_download_request();
            $downloadReq->TheSSLStoreOrderID = $service_fields->thesslstore_order_id;
            if($package->meta->thesslstore_vendor_name == 'DIGICERT'){
                $downloadResp = $api->digicert_download_zip($downloadReq);
            }
            else{
                $downloadResp = $api->order_download_zip($downloadReq);
            }

            if(!$downloadResp->AuthResponse->isError){
                if(strtoupper($downloadResp->CertificateStatus) == 'ACTIVE'){
                    $certdecoded = base64_decode($downloadResp->Zip);
                    $filename = $downloadReq->TheSSLStoreOrderID . '.zip';
                    ob_end_clean();
                    header('Content-type:application/octet-stream');
                    header('Content-Disposition:attachment; filename=' . $filename);
                    echo $certdecoded;
                    exit;
                }
                else{
                    $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.download_cert_invalid_state", true))));
                    return;
                }
            }
            else{
                $this->log($this->api_partner_code . "|download-certificate", json_encode($downloadReq), "input", true);
                $this->log($this->api_partner_code . "|download-certificate", json_encode($downloadResp), "output", false);
                $this->Input->setErrors(array('invalid_action' => array('internal' => $downloadResp->AuthResponse->Message[0])));
                return;
            }
            return 'success';
        }
        else {
            $this->Input->setErrors(array('invalid_action' => array('internal' => Language::_("ThesslstoreModule.!error.invalid_service_status", true))));
            return;
        }
    }
    /**
     *This function is return email content for package email
     *@return string A email content
     */
    private function emailContent(){

        $client = Configure::get("Route.client");
        $WEBDIR=WEBDIR;
        $generation_link = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 's' : '') . '://'.$_SERVER['HTTP_HOST'].$WEBDIR.$client.'/'.'services/manage/{service.id}/tabClientGenerateCert/';
        return $email_content = "
            <p>You've successfully completed the purchasing process for an SSL Certificate! But wait, your SSL still requires a few more steps which can be easily done at the following URL:</p>
            <p><a href=\"{$generation_link}\">{$generation_link}</a></p>
            <p>OR</p>
            <p>If you are using AutoInstall SSL then please follow the below steps:</p>
            <p>Now that your SSL purchase is complete, it's time to set up and install your new SSL certificate automatically!</p>
            <p>To use our AutoInstall SSL technology, the fastest and easiest way to get your new SSL certificate set up, please login to your cPanel/Plesk control panel, click on the AutoInstall SSL icon. Then use the following Token for the automatic installation of Store Order ID : {service.thesslstore_order_id}.</p>
            <p>Token : {service.thesslstore_token}</p>
            <p>You'll be guided through the entire process from there, and it should only take a few minutes.</p>
            <p>If you experience any problems or have any questions throughout the process, please feel free to open a support ticket, we know all the ins and outs of SSL and can quickly help you with any issues. Thank you for trusting us with your web security needs.</p>
        ";
    }
    /**
     * Returns an array of key values for fields stored for a module, package,
     * and service under this module, used to substitute those keys with their
     * actual module, package, or service meta values in related emails.
     *
     * @return array A multi-dimensional array of key/value pairs where each key is one of 'module', 'package', or 'service' and each value is a numerically indexed array of key values that match meta fields under that category.
     * @see Modules::addModuleRow()
     * @see Modules::editModuleRow()
     * @see Modules::addPackage()
     * @see Modules::editPackage()
     * @see Modules::addService()
     * @see Modules::editService()
     */
    public function getEmailTags() {
        return array(
            'module' => array(),
            'package' => array(),
            'service' => array("thesslstore_order_id", "thesslstore_token")
        );
    }

    /**
     * Sends a invite order email when placed only invite order in renew service
     *
     * @param stdClass $service An object representing the service created
     * @param stdClass $package An object representing the package associated with the service
     * @param int $client_id The ID of the client to send the notification to
     */
    private function sendInviteOrderEmail($service, $package, $service_meta) {
        Loader::loadModels($this, array("Clients", "Contacts", "Emails", "ModuleManager"));

        //Replace old service meta beacuse email need to send latest data like new token and order id
        foreach($service_meta as $meta ){
            $meta = (object)$meta;
            foreach($service->fields as $index => $field)
                if($field->key == $meta->key) {
                    $service->fields[$index] = (object)$meta;
                    break;
                }
        }

        // Fetch the client
        $client = $this->Clients->get($service->client_id);

        // Look for the correct language of the email template to send, or default to English
        $service_email_content = null;
        foreach ($package->email_content as $index => $email) {
            // Save English so we can use it if the default language is not available
            if ($email->lang == "en_us")
                $service_email_content = $email;

            // Use the client's default language
            if ($client->settings['language'] == $email->lang) {
                $service_email_content = $email;
                break;
            }
        }

        // Set all tags for the email
        $language_code = ($service_email_content ? $service_email_content->lang : null);

        // Get the module and set the module host name
        $module = $this->ModuleManager->initModule($package->module_id, $package->company_id);
        $module_row = $this->ModuleManager->getRow($service->module_row_id);

        // Set all acceptable module meta fields
        $module_fields = array();
        if (!empty($module_row->meta)) {
            $tags = $module->getEmailTags();
            $tags = (isset($tags['module']) && is_array($tags['module']) ? $tags['module'] : array());

            if (!empty($tags)) {
                foreach ($module_row->meta as $key => $value) {
                    if (in_array($key, $tags))
                        $module_fields[$key] = $value;
                }
            }
        }
        $module = (object)$module_fields;

        // Format package pricing
        if (!empty($service->package_pricing)) {
            Loader::loadModels($this, array("Currencies", "Packages"));

            // Set pricing period to a language value
            $package_period_lang = $this->Packages->getPricingPeriods();
            if (isset($package_period_lang[$service->package_pricing->period]))
                $service->package_pricing->period = $package_period_lang[$service->package_pricing->period];
        }

        // Add each service field as a tag
        if (!empty($service->fields)) {
            $fields = array();
            foreach ($service->fields as $field)
                $fields[$field->key] = $field->value;
            $service = (object)array_merge((array)$service, $fields);
        }

        // Add each package meta field as a tag
        if (!empty($package->meta)) {
            $fields = array();
            foreach ($package->meta as $key => $value)
                $fields[$key] = $value;
            $package = (object)array_merge((array)$package, $fields);
        }

        $tags = array(
            'contact' => $this->Contacts->get($client->contact_id),
            'package' => $package,
            'pricing' => $service->package_pricing,
            'module' => $module,
            'service' => $service,
            'client' => $client,
            'package.email_html' => (isset($service_email_content->html) ? $service_email_content->html : ""),
            'package.email_text' => (isset($service_email_content->text) ? $service_email_content->text : "")
        );

        $this->Emails->send("service_creation", $package->company_id, $language_code, $client->email, $tags, null, null, null, array('to_client_id' => $client->id));
    }

    /**
     * Create a new organization for digicert product
     *
     * @param stdClass $service An object representing the service created
     * @param stdClass $package An object representing the package associated with the service
     * @param array $post Any POST parameters
     * @return organization id if success otherwise false.
     */

    private function createOrganization($service, $package, $post){

        $api = $this->getApi();
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }
        $create_org_req = new digicert_create_organization_request();
        $create_org_req->Name = $post['thesslstore_org_name'];
        $create_org_req->AssumedName = '';
        $create_org_req->Address = $post['thesslstore_admin_address1'];
        $create_org_req->Address2 = $post['thesslstore_admin_address2'];
        $create_org_req->Zip = $post['thesslstore_admin_zip'];
        $create_org_req->City = $post['thesslstore_admin_city'];
        $create_org_req->State = $post['thesslstore_admin_state'];
        $create_org_req->Country = $post['thesslstore_admin_country'];
        $create_org_req->Organization_Phone = $post['thesslstore_admin_phone'];
        $create_org_req->OrganizationContact->Firstname = $post['thesslstore_admin_first_name'];
        $create_org_req->OrganizationContact->Lastname = $post['thesslstore_admin_last_name'];
        $create_org_req->OrganizationContact->Email = $post['thesslstore_admin_email'];
        $create_org_req->OrganizationContact->JobTitle = $post['thesslstore_admin_title'];
        $create_org_req->OrganizationContact->Phone = $post['thesslstore_admin_phone'];

        // if($package->meta->thesslstore_validation_type == 'EV'){
        $create_org_req->ValidationsTypes = 'EV';
        $create_org_req->ApproversContact->Firstname = $post['thesslstore_tech_first_name'];
        $create_org_req->ApproversContact->Lastname = $post['thesslstore_tech_last_name'];
        $create_org_req->ApproversContact->Email = $post['thesslstore_tech_email'];
        $create_org_req->ApproversContact->JobTitle = $post['thesslstore_tech_title'];
        $create_org_req->ApproversContact->Phone = $post['thesslstore_tech_phone'];
        // }
        $create_org_resp = $this->parseResponse($api->digicert_create_organization($create_org_req));

        if($create_org_resp == NULL || $create_org_resp->AuthResponse->isError) {
            return false;
        }
        else{
            $this->Record->insert("sslstore_organisations",
                array('user_id' => $service->client_id,
                    'org_id' => $create_org_resp->OrganizationId,
                    'vendor_org_id' => $create_org_resp->VendorOrganizationId,
                    'org_name' => $post['thesslstore_org_name'],
                    'is_sandbox' => $this->is_sandbox_mode,
                    'created' => date('Y-m-d H:i:s')
                )
            );
        }
        return $create_org_resp->OrganizationId;
    }
    /**
     * Return organization list for DigiCert product
     *
     * @param stdClass $service An object representing the service created
     * @return array organization list.
     */

    private function getOrganizationList($service){
        if (!isset($this->Record)) {
            Loader::loadComponents($this, ['Record']);
        }
        $organisation_list = array();
        $organisation_lists = $this->Record->select(array("org_id","org_name"))->from("sslstore_organisations")->where("user_id", "=", $service->client_id)->where("is_sandbox", "=", $this->is_sandbox_mode)->fetchAll();
        foreach($organisation_lists as $row){
            $organisation_list[$row->org_id] = $row->org_name;
        }

        return $organisation_list;
    }

    /**
     * set Domain approver for DigiCert product
     *
     * @param string $domain_name
     * @param string $org_id
     * @param string $store_order_id
     * @param string $method
     * @return stdClass $domain_info.
     */

    private function setApproverMethod($domain_name, $org_id, $store_order_id, $method){
        $api = $this->getApi();
        $set_approver_method_req = new digicert_set_approver_method_request();
        $set_approver_method_req->DomainName = $domain_name;
        $set_approver_method_req->PreOrganizationId = $org_id;
        $set_approver_method_req->TheSSLStoreOrderID = $store_order_id;
        $set_approver_method_req->ApproverMethod = $method;
        $set_approver_method_req->ValidationsTypes = array('OV','EV');

        $this->log($this->api_partner_code . "|digicert-set-approver", json_encode($set_approver_method_req), "input", true);
        return $this->parseResponse($api->digicert_set_approver_method($set_approver_method_req), true);
    }


    /*
     * Return domain vetting status for pending orders
     *
     * @param stdClass $order_resp
     * @return array Auth details.
     */
    private function getAuthDetails($order_resp){

        $auth_details = array();
        if(strtoupper($order_resp->OrderStatus->MajorStatus) == 'PENDING' || strtoupper($order_resp->OrderStatus->MinorStatus) == 'PENDING_REISSUE'){
            if(isset($order_resp->OrderStatus->DomainAuthVettingStatus) && is_array($order_resp->OrderStatus->DomainAuthVettingStatus)){
                foreach($order_resp->OrderStatus->DomainAuthVettingStatus as $domain_status){
                    if(strtoupper($order_resp->VendorName) != 'SYMANTEC' || (strtoupper($order_resp->VendorName) == 'SYMANTEC' && $order_resp->CommonName == $domain_status->domain)){
                        $auth_details[$domain_status->domain]['dcv_status'] = (strtoupper($domain_status->dcvStatus) == 'VALIDATED' || strtoupper($domain_status->dcvStatus) == 'COMPLETE' || strtoupper($domain_status->dcvStatus) == 'VALID' ? 'Validated' : 'Not Validated');

                        //call digicert get domain info to get latest domain status
                        if(strtoupper($order_resp->VendorName) == 'DIGICERT' && $auth_details[$domain_status->domain]['dcv_status'] == 'Not Validated'){
                            if(empty($domain_status->domain_id)){
                                //set approver method if doesn't set
                                $this->setApproverMethod($domain_status->domain, $order_resp->PreOrganizationId, $order_resp->TheSSLStoreOrderID, 'EMAIL' );
                            }

                            $domain_info = $this->getDomainInfo($domain_status->domain, $order_resp->TheSSLStoreOrderID);
                            if($domain_info != NULL && $domain_info->AuthResponse->isError == false){
                                $domain_status = $domain_info->dcvDetails;
                            }
                        }

                        if(strtoupper($domain_status->dcvMethod) == 'HTTP_CSR_HASH' || strtoupper($domain_status->dcvMethod) == 'HTTPS_CSR_HASH' || strtoupper($domain_status->dcvMethod) == 'FILE'){
                            $auth_details[$domain_status->domain]['method'] = 'FILE';
                            $protocol = 'http';
                            $auth_details[$domain_status->domain]['is_https'] = false;
                            if($domain_status->dcvMethod == 'HTTPS_CSR_HASH'){
                                $protocol = 'https';
                                $auth_details[$domain_status->domain]['is_https'] = true;
                            }
                            $file_name = explode("/", $domain_status->FileName);
                            $auth_details[$domain_status->domain]['file_name'] = array_pop($file_name);
                            $auth_details[$domain_status->domain]['file_url'] = $protocol . "://" . str_replace('*.', '', $domain_status->domain) . "/.well-known/pki-validation/" . $auth_details[$domain_status->domain]['file_name'];
                            $auth_details[$domain_status->domain]['file_content'] = $domain_status->FileContents;
                        }
                        elseif(strtoupper($domain_status->dcvMethod) == 'CNAME_CSR_HASH'){
                            $auth_details[$domain_status->domain]['method'] = 'CNAME';
                            $auth_details[$domain_status->domain]['alias'] = $domain_status->DNSName;
                            $auth_details[$domain_status->domain]['point_to'] = $domain_status->DNSEntry;
                        }
                        elseif(strtoupper($domain_status->dcvMethod) == 'DNS'){
                            $auth_details[$domain_status->domain]['method'] = 'DNS';
                            $auth_details[$domain_status->domain]['point_to'] = $domain_status->DNSEntry;
                        }
                        else{
                            $auth_details[$domain_status->domain]['method'] = 'EMAIL';
                            if(strtoupper($order_resp->VendorName) == 'DIGICERT'){
                                $auth_details[$domain_status->domain]['email'] = 'WHOIS EMAILS';
                            }
                            else{
                                $auth_details[$domain_status->domain]['email'] = $domain_status->dcvMethod;
                            }
                        }
                    }
                }
            }
        }
        return $auth_details;
    }
    /*
     * Return domain vetting status for DigiCert product
     * @param $domain_name
     * @patam $store_order_id
     * @return stdClass getDomainInfo
     */
    private function getDomainInfo($domain_name, $store_order_id){
        $api = $this->getApi();
        $get_domain_info_req = new digicert_get_domain_request();
        $get_domain_info_req->DomainName = $domain_name;
        $get_domain_info_req->TheSSLStoreOrderID = $store_order_id;

        $get_domain_info_resp = $api->digicert_get_domain_info($get_domain_info_req);


        if($get_domain_info_resp == NULL || $get_domain_info_resp->AuthResponse->isError){
            $this->log($this->api_partner_code . "|digicert-get-domain-info", json_encode($get_domain_info_req), "input", true);
            $this->log($this->api_partner_code . "|digicert-get-domain-info", json_encode($get_domain_info_req), "output", true);
        }
        return $get_domain_info_resp;
    }
    /*
     * Return Web Server List
     */

    private function getWebserverTypes($vendor_name){

        if($vendor_name == 'DIGICERT'){
            return array(
                "" => Language::_("ThesslstoreModule.please_select", true),
                "apachessl" => "Apache + MOD SSL",
                "Barracuda" => "Barracuda",
                "BEA Weblogic 8 & 9" => "BEA Weblogic 8 & 9",
                "cisco3000" => "Cisco 3000 Series VPN Concentrator",
                "citrix" => "Citrix",
                "Citrix Access Essentials" => "Citrix Access Essentials",
                "Citrix Access Gateway 4.x" => "Citrix Access Gateway 4.x",
                "Citrix Access Gateway 5.x and higher" => "Citrix Access Gateway 5.x and higher",
                "cpanel" => "Cpanel",
                "F5 Big-IP" => "F5 Big-IP",
                "F5 FirePass" => "F5 FirePass",
                "Ibmhttp" => "IBM HTTP",
                "tomcat" => "Jakart-Tomcat",
                "javawebserver" => "Java Web Server (Javasoft / Sun)",
                "Juniper" => "Juniper",
                "Lighttpd" => "Lighttpd",
                "Domino" => "Lotus Domino 4.6+",
                "Mac OS X Server" => "Mac OS X Server",
                "Microsoft Exchange Server 2003" => "Microsoft Exchange Server 2003",
                "Microsoft Exchange Server 2007" => "Microsoft Exchange Server 2007",
                "Microsoft Exchange Server 2010" => "Microsoft Exchange Server 2010",
                "Microsoft Exchange Server 2013" => "Microsoft Exchange Server 2013",
                "Microsoft Exchange Server 2016" => "Microsoft Exchange Server 2016",
                "Microsoft Forefront Unified Access Gateway" => "Microsoft Forefront Unified Access Gateway",
                "iis4" => "Microsoft IIS 4.0",
                "iis5" => "Microsoft IIS 5.0",
                "Microsoft IIS 8" => "Microsoft IIS 8",
                "Microsoft IIS 10" => "Microsoft IIS 10",
                "iis" => "Microsoft Internet Information Server",
                "Microsoft Live Communications Server 2005" => "Microsoft Live Communications Server 2005",
                "Microsoft Lync Server 2010" => "Microsoft Lync Server 2010",
                "Microsoft Lync Server 2013" => "Microsoft Lync Server 2013",
                "Microsoft OCS R2" => "Microsoft OCS R2",
                "Microsoft Office Communications Server 2007" => "Microsoft Office Communications Server 2007",
                "Microsoft Small Business Server 2008 & 2011" => "Microsoft Small Business Server 2008 & 2011",
                "Netscape" => "Netscape Enterprise/FastTrack",
                "NetscapeFastTrack" => "Netscape FastTrack",
                "nginx" => "Nginx",
                "Novell iChain" => "Novell iChain",
                "Novell NetWare" => "Novell NetWare",
                "oracle" => "Oracle",
                "Qmail" => "Qmail",
                "SunOne" => "SunOne",
                "WebLogic" => "WebLogic â€“ all versions",
                "webstar" => "WebStar",
                "zeusv3" => "Zeus v3+",
                "other" => "Other"
            );
        }
        else{

            return array(
                "" => Language::_("ThesslstoreModule.please_select", true),
                "aol" => "AOL",
                "apacheapachessl" => "Apache + ApacheSSL",
                "apachessl" => "Apache + MOD SSL",
                "apacheopenssl" => "Apache + OpenSSL",
                "apacheraven" => "Apache + Raven",
                "apachessleay" => "Apache + SSLeay",
                "apache2" => "Apache 2",
                "c2net" => "C2Net Stronghold",
                "cisco3000" => "Cisco 3000 Series VPN Concentrator",
                "citrix" => "Citrix",
                "cobaltseries" => "Cobalt Series",
                "covalentserver" => "Covalent Server Software",
                "cpanel" => "Cpanel",
                "ensim" => "Ensim",
                "hsphere" => "Hsphere",
                "Iplanet" => "iPlanet Server 4.1",
                "Ibmhttp" => "IBM HTTP",
                "Ibminternet" => "IBM Internet Connection Server",
                "ipswitch" => "Ipswitch",
                "tomcat" => "Jakart-Tomcat",
                "javawebserver" => "Java Web Server (Javasoft / Sun)",
                "Domino" => "Lotus Domino 4.6+",
                "Dominogo4625" => "Lotus Domino Go 4.6.2.51",
                "Dominogo4626" => "Lotus Domino Go 4.6.2.6+",
                "iis4" => "Microsoft IIS 4.0",
                "iis5" => "Microsoft IIS 5.0",
                "iis" => "Microsoft Internet Information Server",
                "Netscape" => "Netscape Enterprise/FastTrack",
                "NetscapeFastTrack" => "Netscape FastTrack",
                "website" => "O'Reilly WebSite Professional",
                "oracle" => "Oracle",
                "plesk" => "Plesk",
                "quid" => "Quid Pro Quo",
                "r3ssl" => "R3 SSL Server",
                "reven" => "Raven SSL",
                "redhat" => "RedHat Linux",
                "sapwebserver" => "SAP Web Application Server",
                "WebLogic" => "WebLogic â€“ all versions",
                "webstar" => "WebStar",
                "webten" => "WebTen (from Tenon)",
                "zeusv3" => "Zeus v3+",
                "other" => "Other"
            );
        }
    }

    /* Return Country List*/

    private function getCountryList(){
        return array(
            '' => Language::_("ThesslstoreModule.please_select", true),
            'AF'=>'Afghanistan','AX'=>'Aland Islands','AL'=>'Albania','DZ'=>'Algeria','AS'=>'American Samoa','AD'=>'Andorra',
            'AO'=>'Angola','AI'=>'Anguilla','AQ'=>'Antarctica','AG'=>'Antigua and Barbuda','AR'=>'Argentina','AM'=>'Armenia','AW'=>'Aruba',
            'AC'=>'Ascension Island','AU'=>'Australia','AT'=>'Austria','AZ'=>'Azerbaijan','BS'=>'Bahamas','BH'=>'Bahrain','BD'=>'Bangladesh',
            'BB'=>'Barbados','BY'=>'Belarus','BE'=>'Belgium','BZ'=>'Belize','BJ'=>'Benin','BM'=>'Bermuda','BT'=>'Bhutan','BO'=>'Bolivia',
            'BQ'=>'Bonaire, Sint Eustatius, and Saba',
            'BA'=>'Bosnia and Herzegovina','BW'=>'Botswana','BV'=>'Bouvet Island','BR'=>'Brazil','IO'=>'British Indian Ocean Territory',
            'VG'=>'British Virgin Islands','BN'=>'Brunei','BG'=>'Bulgaria','BF'=>'Burkina Faso','BI'=>'Burundi','KH'=>'Cambodia','CM'=>'Cameroon',
            'CA'=>'Canada','IC'=>'Canary Islands','CV'=>'Cape Verde','KY'=>'Cayman Islands','CF'=>'Central African Republic','EA'=>'Ceuta and Melilla',
            'TD'=>'Chad','CL'=>'Chile','CN'=>'China','CX'=>'Christmas Island','CP'=>'Clipperton Island','CC'=>'Cocos [Keeling] Islands','CO'=>'Colombia',
            'KM'=>'Comoros','CG'=>'Congo - Brazzaville','CD'=>'Congo - Kinshasa','CK'=>'Cook Islands','CR'=>'Costa Rica','CI'=>'Cote D\'Ivoire','HR'=>'Croatia',
            'CU'=>'Cuba','CW'=>'Curaçao','CY'=>'Cyprus','CZ'=>'Czech Republic','DK'=>'Denmark','DG'=>'Diego Garcia','DJ'=>'Djibouti','DM'=>'Dominica',
            'DO'=>'Dominican Republic','EC'=>'Ecuador','EG'=>'Egypt','SV'=>'El Salvador','GQ'=>'Equatorial Guinea','ER'=>'Eritrea','EE'=>'Estonia',
            'ET'=>'Ethiopia','EU'=>'European Union','FK'=>'Falkland Islands','FO'=>'Faroe Islands','FJ'=>'Fiji','FI'=>'Finland','FR'=>'France',
            'GF'=>'French Guiana','PF'=>'French Polynesia','TF'=>'French Southern Territories','GA'=>'Gabon','GM'=>'Gambia','GE'=>'Georgia',
            'DE'=>'Germany','GH'=>'Ghana','GI'=>'Gibraltar','GR'=>'Greece','GL'=>'Greenland','GD'=>'Grenada','GP'=>'Guadeloupe','GU'=>'Guam',
            'GT'=>'Guatemala','GG'=>'Guernsey','GN'=>'Guinea','GW'=>'Guinea-Bissau','GY'=>'Guyana','HT'=>'Haiti','HM'=>'Heard Island and McDonald Islands',
            'HN'=>'Honduras','HK'=>'Hong Kong SAR China','HU'=>'Hungary','IS'=>'Iceland','IN'=>'India','ID'=>'Indonesia','IR'=>'Iran','IQ'=>'Iraq',
            'IE'=>'Ireland','IM'=>'Isle of Man','IL'=>'Israel','IT'=>'Italy','JM'=>'Jamaica','JP'=>'Japan','JE'=>'Jersey','JO'=>'Jordan',
            'KZ'=>'Kazakhstan','KE'=>'Kenya','KI'=>'Kiribati','KW'=>'Kuwait','KG'=>'Kyrgyzstan','LA'=>'Laos','LV'=>'Latvia','LB'=>'Lebanon',
            'LS'=>'Lesotho','LR'=>'Liberia','LY'=>'Libya','LI'=>'Liechtenstein','LT'=>'Lithuania','LU'=>'Luxembourg','MO'=>'Macau SAR China',
            'MK'=>'Macedonia','MG'=>'Madagascar','MW'=>'Malawi','MY'=>'Malaysia','MV'=>'Maldives','ML'=>'Mali','MT'=>'Malta','MH'=>'Marshall Islands',
            'MQ'=>'Martinique','MR'=>'Mauritania','MU'=>'Mauritius','YT'=>'Mayotte','MX'=>'Mexico','FM'=>'Micronesia','MD'=>'Moldova','MC'=>'Monaco',
            'MN'=>'Mongolia','ME'=>'Montenegro','MS'=>'Montserrat','MA'=>'Morocco','MZ'=>'Mozambique','MM'=>'Myanmar [Burma]','NA'=>'Namibia',
            'NR'=>'Nauru','NP'=>'Nepal','NL'=>'Netherlands','AN'=>'Netherlands Antilles','NC'=>'New Caledonia','NZ'=>'New Zealand',
            'NI'=>'Nicaragua','NE'=>'Niger','NG'=>'Nigeria','NU'=>'Niue','NF'=>'Norfolk Island','KP'=>'North Korea','MP'=>'Northern Mariana Islands',
            'NO'=>'Norway','OM'=>'Oman','QO'=>'Outlying Oceania','PK'=>'Pakistan','PW'=>'Palau','PS'=>'Palestinian Territories','PA'=>'Panama',
            'PG'=>'Papua New Guinea','PY'=>'Paraguay','PE'=>'Peru','PH'=>'Philippines','PN'=>'Pitcairn Islands','PL'=>'Poland','PT'=>'Portugal',
            'PR'=>'Puerto Rico','QA'=>'Qatar','RE'=>'Réunion','RO'=>'Romania','RU'=>'Russia','RW'=>'Rwanda','BL'=>'Saint Barthélemy',
            'SH'=>'Saint Helena','KN'=>'Saint Kitts and Nevis','LC'=>'Saint Lucia','MF'=>'Saint Martin','PM'=>'Saint Pierre and Miquelon',
            'VC'=>'Saint Vincent and the Grenadines','WS'=>'Samoa','SM'=>'San Marino','ST'=>'São Tomé and Príncipe','SA'=>'Saudi Arabia',
            'SN'=>'Senegal','RS'=>'Serbia','CS'=>'Serbia and Montenegro','SC'=>'Seychelles','SL'=>'Sierra Leone','SG'=>'Singapore','SX'=>'Sint Maarten',
            'SK'=>'Slovakia','SI'=>'Slovenia','SB'=>'Solomon Islands','SO'=>'Somalia','ZA'=>'South Africa',
            'GS'=>'South Georgia and the South Sandwich Islands','KR'=>'South Korea','SS'=>'South Sudan','ES'=>'Spain',
            'LK'=>'Sri Lanka','SD'=>'Sudan','SR'=>'Suriname','SJ'=>'Svalbard and Jan Mayen','SZ'=>'Swaziland','SE'=>'Sweden',
            'CH'=>'Switzerland','SY'=>'Syria','TW'=>'Taiwan','TJ'=>'Tajikistan','TZ'=>'Tanzania','TH'=>'Thailand','TL'=>'Timor-Leste','TG'=>'Togo',
            'TK'=>'Tokelau','TO'=>'Tonga','TT'=>'Trinidad and Tobago','TA'=>'Tristan da Cunha','TN'=>'Tunisia','TR'=>'Turkey','TM'=>'Turkmenistan',
            'TC'=>'Turks and Caicos Islands','TV'=>'Tuvalu','UM'=>'U.S. Minor Outlying Islands','VI'=>'U.S. Virgin Islands','UG'=>'Uganda',
            'UA'=>'Ukraine','AE'=>'United Arab Emirates','GB'=>'United Kingdom','US'=>'United States','UY'=>'Uruguay','UZ'=>'Uzbekistan',
            'VU'=>'Vanuatu','VA'=>'Vatican City','VE'=>'Venezuela','VN'=>'Vietnam','WF'=>'Wallis and Futuna','EH'=>'Western Sahara',
            'YE'=>'Yemen','ZM'=>'Zambia','ZW'=>'Zimbabwe'

        );
    }




    /**
     * Parses the response from TheSslStore into an stdClass object
     *
     * @param mixed $response The response from the API
     * @param stdClass $module_row A stdClass object representing a single reseller (optional, required when Module::getModuleRow() is unavailable)
     * @param boolean $ignore_error Ignores any response error and returns the response anyway; useful when a response is expected to fail (optional, default false)
     * @return stdClass A stdClass object representing the response, void if the response was an error
     */
    private function parseResponse($response, $ignore_error = false) {
        Loader::loadHelpers($this, array("Html"));

        /*echo "<pre>";
        print_r($response);*/

        $success = true;

        if(empty($response)) {
            $success = false;

            if (!$ignore_error)
                $this->Input->setErrors(array('api' => array('internal' => Language::_("ThesslstoreModule.!error.api.internal", true))));
        }
        elseif ($response) {
            $auth_response = null;
            if (is_array($response) && isset($response[0]) && $response[0] && is_object($response[0]) && property_exists($response[0], "AuthResponse"))
                $auth_response = $response[0]->AuthResponse;
            elseif (is_object($response) && $response && property_exists($response, "AuthResponse"))
                $auth_response = $response->AuthResponse;
            elseif(is_object($response) && $response && property_exists($response, "isError"))
                $auth_response = $response;

            if ($auth_response && property_exists($auth_response, "isError") && $auth_response->isError) {
                $success = false;
                $error_message = (property_exists($auth_response, "Message") && isset($auth_response->Message[0]) ? $auth_response->Message[0] : Language::_("TheSSLStore.!error.api.internal", true));

                if (!$ignore_error)
                    $this->Input->setErrors(array('api' => array('internal' => $error_message)));
            }
            elseif ($auth_response === null) {
                $success = false;

                if (!$ignore_error)
                    $this->Input->setErrors(array('api' => array('internal' => Language::_("TheSSLStore.!error.api.internal", true))));

            }
        }

        // Break the response into segments no longer than the max length that can be logged
        // (i.e. 64KB = 65535 characters)
        $responses = str_split(serialize($response), 65535);

        foreach ($responses as $log) {
            $this->log($this->api_partner_code, $log, "output", $success);
        }

        if (!$success && !$ignore_error)
            return;

        return $response;
    }

    /*
     * Return primary domain name
     */
    private function getPrimaryDomain($domain_name){
        if ( !preg_match("/^http/", $domain_name) )
            $domain_name = 'http://' . $domain_name;
        if ( $domain_name[strlen($domain_name)-1] != '/' )
            $domain_name .= '/';
        $pieces = parse_url($domain_name);
        $domain = isset($pieces['host']) ? $pieces['host'] : '';
        if ( preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs) ) {
            $res = preg_replace('/^www\./', '', $regs['domain'] );
            return $res;
        }
        return $domain_name;
    }
}

if(isset($_POST['action']) && $_POST['action'] == 'getResellerPrice'){
    $tssObject = new ThesslstoreModule();
    $apiCurrencyRate = $_POST['apiCurrencyRate'];
    //get selected currency rate

    /* Retrieve the company ID */
    $companyID=Configure::get("Blesta.company_id");

    // Load the Loader to fetch supported Currencies
    Loader::loadModels($tssObject, array("Currencies"));

    $getSelectedCurrency = $tssObject->Currencies-> get($_POST['selectedCurrencyId'],$companyID);

    Loader::load(dirname(__FILE__) . DS . "api" . DS . "thesslstoreApi.php");


    $pricing = array();
    //Get products
    $products = $tssObject->getProducts();
    /*------------------ Currency Converter Function ---------------------------*/
    function thesslstore_currecncy_converter($productprice, $currency_rate, $api_currency_rate){

        $final_price = (($productprice * $currency_rate)/$api_currency_rate);

        return number_format($final_price, 2, '.', '');
    }

    if($products != NULL && $getSelectedCurrency != NULL){
        foreach($products as $product){
            if($product->ProductCode == 'freessl'){
                $pricing[$product->ProductCode . '_1'] = thesslstore_currecncy_converter(0, $getSelectedCurrency->exchange_rate, $apiCurrencyRate);
            }
            else {
                foreach ($product->PricingInfo as $pinfo) {
                    $pricing[$product->ProductCode . '_' . $pinfo->NumberOfMonths] = thesslstore_currecncy_converter($pinfo->Price, $getSelectedCurrency->exchange_rate, $apiCurrencyRate);
                    if (($product->MaxSan - $product->MinSan - 1) > 0) {
                        $pricing[$product->ProductCode . '_' . $pinfo->NumberOfMonths . '_san'] = thesslstore_currecncy_converter($pinfo->PricePerAdditionalSAN, $getSelectedCurrency->exchange_rate, $apiCurrencyRate);
                    }
                    if ($product->isNoOfServerFree == false && $product->isCodeSigning == false && $product->isScanProduct == false) {
                        $pricing[$product->ProductCode . '_' . $pinfo->NumberOfMonths . '_server'] = thesslstore_currecncy_converter($pinfo->PricePerAdditionalServer, $getSelectedCurrency->exchange_rate, $apiCurrencyRate);
                    }
                }
            }
        }
    }

    echo json_encode(array(
            "prefix" => $getSelectedCurrency->prefix,
            "suffix" => $getSelectedCurrency->suffix,
            "pricing" => $pricing
        )
    );die();
}