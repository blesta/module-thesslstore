<?php
/**
 * User: Parag Mehta<parag@paragm.com>
 * Date: 2/27/12
 * Time: 7:27 AM
 * This file is created by www.thesslstore.com for your use. You are free to change the file as per your needs.
 */

include_once "abstractions.php";

class csr_request extends baserequest
{
    public $ProductCode = '';
    public $CSR = '';
}

class ssl_validation_request extends baserequest
{
    public $Domainname;
}

class whois_request extends baserequest
{
    public $Domainname;
}

class free_claimfree_request extends baserequest
{
    public function __construct()
    {
        $this->NewOrderRequest = new order_neworder_request_freeproduct();
        parent::__construct();
    }
    public $ProductCode;
    public $RelatedTheSSLStoreOrderID;
    public $NewOrderRequest;
}


class free_cuinfo_request extends baserequest
{
    public function __construct()
    {
        $this->OrganizationInfo = new OrganizationInfo();
        $this->OrganizationInfo->OrganizationAddress = new OrganizationAddress();
        $this->AdminContact = new contact();
        $this->TechnicalContact = new contact();
        parent::__construct();
    }

    public $CustomOrderID;
    public $ProductCode;
    public $ExtraProductCodes;
    public $OrganizationInfo;
    public $ValidityPeriod;
    public $ServerCount;
    public $CSR;
    public $DomainName;
    public $WebServerType;
    public $DNSNames;
    public $isCUOrder;
    public $isRenewalOrder;
    public $SpecialInstructions;
    public $RelatedTheSSLStoreOrderID;
    public $isTrialOrder;
    public $AdminContact;
    public $TechnicalContact;
    public $ApproverEmail;
    public $ReserveSANCount;
    public $AddInstallationSupport;
    public $EmailLanguageCode;
    public $FileAuthDVIndicator;
    public $CNAMEAuthDVIndicator;
    public $HTTPSFileAuthDVIndicator;
    public $SignatureHashAlgorithm;
}

class health_validate_request
{
    public $PartnerCode;
    public $AuthToken;
    public $ReplayToken;
    public $UserAgent;
}

class health_validate_token_request
{
    public $Token;
    public $TokenID;
    public $TokenCode;
    public $IsUsedForTokenSystem = true;
}

class order_agreement_request extends baserequest
{
    public function __construct()
    {
        $this->OrganizationInfo = new OrganizationInfo();
        $this->OrganizationInfo->OrganizationAddress = new OrganizationAddress();
        parent::__construct();
    }
    public $CustomOrderID;
    public $ProductCode;
    public $ExtraProductCodes;
    public $OrganizationInfo;
    public $ValidityPeriod;
    public $ServerCount;
    public $CSR;
    public $DomainName;
    public $WebServerType;
}

class order_download_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $ResendEmail;
    public $RefundReason;
    public $RefundRequestID;
    public $ApproverMethod;
    public $DomainNames;
    public $SerialNumber;
    public $ReturnPKCS7Cert;
    public $DateTimeCulture;
}

class order_download_zip_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $ResendEmail;
    public $RefundReason;
    public $RefundRequestID;
    public $ApproverMethod;
    public $DomainNames;
    public $SerialNumber;
    public $ReturnPKCS7Cert;
    public $DateTimeCulture;
}

class order_inviteorder_request extends baserequest
{
    public $PreferVendorLink;
    public $ProductCode;
    public $ExtraProductCode;
    public $ServerCount;
    public $RequestorEmail;
    public $ExtraSAN;
    public $CustomOrderID;
    public $ValidityPeriod;
    public $AddInstallationSupport;
    public $SignatureHashAlgorithm;
    public $EmailLanguageCode;
    public $PreferSendOrderEmails;
    public $CertTransparencyIndicator;
    public $DateTimeCulture;
}

class order_neworder_request extends baserequest
{
    public function __construct()
    {
        $this->OrganizationInfo = new OrganizationInfo();
        $this->OrganizationInfo->OrganizationAddress = new OrganizationAddress();
        $this->AdminContact= new contact();
        $this->TechnicalContact= new contact();
        parent::__construct();
    }
    public $CustomOrderID;
    public $ProductCode;
    public $ExtraProductCodes;
    public $OrganizationInfo;
    public $ValidityPeriod;
    public $ServerCount;
    public $CSR;
    public $DomainName;
    public $WebServerType;
    public $DNSNames;
    public $isCUOrder;
    public $isRenewalOrder;
    public $SpecialInstructions;
    public $RelatedTheSSLStoreOrderID;
    public $isTrialOrder;
    public $AdminContact;
    public $TechnicalContact;
    public $ApproverEmail;
    public $ReserveSANCount;
    public $AddInstallationSupport;
    public $EmailLanguageCode;
    public $FileAuthDVIndicator;
    public $CNAMEAuthDVIndicator;
    public $HTTPSFileAuthDVIndicator;
    public $SignatureHashAlgorithm;
    public $CertTransparencyIndicator;
    public $RenewalDays;
    public $DateTimeCulture;
    public $CSRUniqueValue;
    public $PreOrganizationId;
}


class order_neworder_request_freeproduct extends baserequest
{
    public function __construct()
    {
        $this->TechnicalContact= new contact();
        parent::__construct();
    }
    
    public $TechnicalContact;
    
}
class order_validate_request extends baserequest
{
    public $CSR;
    public $ProductCode;
    public $ServerCount;
    public $ValidityPeriod;
    public $WebServerType;
}
class order_query_request extends baserequest
{
    public $StartDate;
    public $EndDate;
    public $CertificateExpireToDate;
    public $CertificateExpireFromDate;
    public $DomainName;
    public $SubUserID;
    public $ProductCode;
    public $DateTimeCulture;
    public $PageNumber;
    public $PageSize;
}

class order_modified_summary_request extends baserequest
{
    public $SubUserID;
    public $StartDate;
    public $EndDate;
    public $PageNumber;
    public $PageSize;
}

class order_certificaterevokerequest_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmail;
    public $SerialNumber;
}

class order_vulnerabilityscanrequest_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ActionType;
}

class order_refundrequest_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $ResendEmail;
    public $RefundReason;
    public $RefundRequestID;
    public $ApproverMethod;
    public $DomainNames;
    public $SerialNumber;
}

class order_refundstatus_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $ResendEmail;
    public $RefundReason;
    public $RefundRequestID;
    public $ApproverMethod;
    public $DomainNames;
    public $SerialNumber;
}

class order_reissue_request extends baserequest
{
    public function __construct()
    {
        $this->EditSAN = array();
        $this->DeleteSAN = array();
        $this->AddSAN = array();
        parent::__construct();
    }
    public $TheSSLStoreOrderID;
    public $CSR;
    public $WebServerType;
    public $DNSNames;
    public $SpecialInstructions;
    public $EditSAN;
    public $DeleteSAN;
    public $AddSAN;
    public $isWildCard;
    public $ReissueEmail;
    public $PreferEnrollmentLink;
    public $SignatureHashAlgorithm;
    public $FileAuthDVIndicator;
    public $HTTPSFileAuthDVIndicator;
    public $CNAMEAuthDVIndicator;
    public $ApproverEmails;
    public $CertTransparencyIndicator;
    public $DateTimeCulture;
    public $CSRUniqueValue;
}

class order_resend_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $ResendEmail;
    public $RefundReason;
    public $RefundRequestID;
    public $ApproverMethod;
    public $DomainNames;
    public $SerialNumber;
}

class order_approverlist_request extends baserequest
{
    
    public $ProductCode;
    public $DomainName;
}

class order_changeapproveremail_request extends baserequest
{
    public $TheSSLStoreOrderID;
    public $ResendEmail;
    public $ApproverMethod;
    public $DomainNames;
}

class order_change_approver_method_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $ResendEmail;
    public $RefundReason;
    public $RefundRequestID;
    public $ApproverMethod;
    public $DomainNames;
    public $SerialNumber;
}

class order_status_request extends baserequest
{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $ResendEmailType;
    public $ResendEmail;
    public $RefundReason;
    public $RefundRequestID;
    public $ApproverMethod;
    public $DomainNames;
    public $SerialNumber;
    public $ReturnPKCS7Cert;
    public $DateTimeCulture;
}

class order_pmr_request extends baserequest{
    public $TheSSLStoreOrderID;
}


class product_query_request extends baserequest
{
    public $ProductCode;
    public $ProductType;
    public $NeedSortedList;
}

class setting_setordercallback_request extends baserequest
{
    public $url;
}

class setting_setpricecallback_request extends baserequest
{
    public $url;
}

class setting_settemplate_request extends baserequest
{
    public $EmailSubject;
    public $EmailMessage;
    public $isDisabled;
    public $EmailTemplateTypes;
}

class setting_cancelnotification_request extends baserequest
{
    public $url;
}

class user_add_request extends baserequest
{
    public $PartnerCode;
    public $CustomPartnerCode;
    public $AuthenticationToken;
    public $PartnerEmail;
    public $isEnabled;
}

class user_activate_request extends baserequest
{
    public $PartnerCode;
    public $CustomPartnerCode;
    public $AuthenticationToken;
    public $PartnerEmail;
    public $isEnabled;
}

class user_deactivate_request extends baserequest
{
    public $PartnerCode;
    public $CustomPartnerCode;
    public $AuthenticationToken;
    public $PartnerEmail;
    public $isEnabled;
}

class user_query_request extends baserequest
{
    public $SubUserID;
    public $StartDate;
    public $EndDate;
}

class user_newuser_request extends baserequest
{  
   public $Email;
   public $Password;
   public $FirstName;
   public $LastName;
   public $AlternateEmail;
   public $CompanyName;
   public $Street;
   public $CountryName;
   public $State;
   public $City;
   public $Zip;
   public $Phone;
   public $Fax;
   public $Mobile;
   public $UserType;
   public $HearedBy;
}

class order_replacement_request extends baserequest
{
    /* public $CustomOrderID;
     public $TheSSLStoreOrderID;
     public $ActionType;
     public $Status;
     public $ReplaceByDate;
     public $PageNumber;
     public $PageSize;*/
}

class user_account_detail_request extends baserequest
{
    public $PartnerCode;
    public $AuthToken;
    public $ReplayToken;
    public $UserAgent;
    public $IPAddress;
}

class csr_download_request extends baseRequest{
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
}

class cwatch_product_list_request extends baserequest{
    public $ProductCode;
}

class cwatch_invite_order_request extends baserequest
{
    public $ProductCode;
    public $RequestorEmail;
    public $CustomOrderID;
    public $ValidityPeriod;
    public $EmailLanguageCode;
    public $PreferSendOrderEmails;
}

class cwatch_new_order_request extends baserequest
{
    public function __construct(){
        $this->AdminContact = new cwatch_admin_contact();
        parent::__construct();
    }
    public $autoLicenseUpgrade;
    public $automaticRenewal;
    public $CustomOrderID;
    public $ProductCode;
    public $ValidityPeriod;
    public $DomainName;
    public $SpecialInstructions;
    public $AdminContact;
    public $ApproverEmail;
    public $EmailLanguageCode;
}

class cwatch_update_site_request extends baserequest{
    public $TheSSLStoreOrderID;
    public $DomainName;
}

class cwatch_upgrade_license_request extends baserequest{
    public $TheSSLStoreOrderID;
    public $ValidityPeriod;
    public $ProductCode;
}


class digicert_create_organization_request extends baserequest{
    public function __construct(){
        $this->OrganizationContact = new digicert_organization_contact();
        $this->ApproversContact = new digicert_organization_contact();
        parent::__construct();
    }
    public $Name;
    public $AssumedName;
    public $Address;
    public $Address2;
    public $Zip;
    public $City;
    public $State;
    public $Country;
    public $Organization_Phone;
    public $OrganizationContact;
    public $ApproversContatct;
}

class digicert_get_organization_request extends baserequest{
    public $OrganizationId;
}

class digicert_organization_list_request extends baserequest{

}

class digicert_set_approver_method_request extends baserequest{
    public $DomainName;
    public $PreOrganizationId;
    public $TheSSLStoreOrderID;
    public $ValidationsTypes;
    public $ApproverMethod;
}

class digicert_get_domain_request extends baseRequest{
    public $DomainName;
    public $TheSSLStoreOrderID;
}

