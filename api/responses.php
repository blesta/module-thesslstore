<?php
/**
 * User: Parag Mehta<parag@paragm.com>
 * Date: 2/27/12
 * Time: 7:27 AM
 * This file is created by www.thesslstore.com for your use. You are free to change the file as per your needs.
 */

include_once "abstractions.php";

class csr_response extends baseresponse
{
    public $DomainName;
    public $DNSNames;
    public $Country;
    public $Email;
    public $Locality;
    public $Organization;
    public $OrganizationUnit;
    public $State;
    public $hasBadExtensions = false;
    public $isValidDomainName = false;
    public $isWildcardCSR = false;
    public $MD5Hash;
    public $SHA1Hash;
    public $sha256;
    public $RegionSpecificOrderIndicator;
}

class ssl_validation_response extends baseresponse
{
    public $DomainName;
    public $CommonName;
    public $ChainRoot;
    public $Subject;
    public $Organization;
    public $OrganizationUnit;
    public $Country;
    public $State;
    public $Location;
    public $SerialNumber;
    public $PublicKey;
    public $KeySize;
    public $Issuer;
    public $IssuerName;
    public $KeyAlgorithmParameters;
    public $KeyAlgorithm;
    public $HashCode;
    public $Format;
    public $ExpirationDate;
    public $EffectiveDate;
    public $SANs;
    public $Version;
    public $ThumbPrint;
    public $SignatureAlgorithm;
    public $CertHash;
    public $CertificateType;
    public $Verify;
    
}

class whois_response extends baseresponse
{
    public $WhoisDetails;
}

class free_claimfree_response extends baseresponse
{
    public $isAllowed;
    public $PartnerOrderID;
    public $TheSSLStoreOrderID;
    public $VendorOrderID;
    public $LoginName;
    public $LoginPassword;
}

class free_cuinfo_response extends baseresponse
{
    public $isSupported;
    public $Months;
    public $SerialNumber;
    public $ExpirationDate;
    public $Issuer;
}

class health_validate_response
{
    public $Status;
}

class health_validate_token_response extends baseresponse
{
    public function __construct()
    {
        $this->OrderStatus = new orderStatus();
        parent::__construct();
    }
    public $ProductName;
    public $ProductCode;
    public $San;
    public $OrderStatus;
    public $NumberOfMonths;
    public $ServerCount;
    public $isRenewalOrder;
    public $ProductType;
    public $VendorName;
}




class order_response extends baseresponse
{
    public function __construct()
    {
        $this->OrderStatus = new orderStatus();
        $this->AdminContact = new contact();
        $this->TechnicalContact = new contact();
        parent::__construct();
    }
    public $PartnerOrderID;
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $VendorOrderID;
    public $RefundRequestID;
    public $isRefundApproved;
    public $TinyOrderLink;
    public $OrderStatus;
    public $OrderAmount;
    public $PurchaseDate;
    public $CertificateStartDate;
    public $CertificateEndDate;
    public $CommonName;
    public $DNSNames;
    public $SANCount;
    public $ServerCount;
    public $Validity;
    public $Organization;
    public $OrganizationalUnit;
    public $State;
    public $Country;
    public $Locality;
    public $OrganizationPhone;
    public $OrganizationAddress;
    public $OrganizationPostalcode;
    public $DUNS;
    public $WebServerType;
    public $ApproverEmail;
    public $ProductName;
    public $AdminContact;
    public $TechnicalContact;
    public $ReissueSuccessCode;
    public $AuthFileName;
    public $AuthFileContent;
    public $PollStatus;
    public $PollDate;
    public $CustomerLoginName;
    public $CustomerPassword;
    public $CustomerID;
    public $TokenID;
    public $TokenCode;
    public $SiteSealurl;
    public $CNAMEAuthName;
    public $CNAMEAuthValue;
    public $SignatureEncryptionAlgorithm;
    public $SignatureHashAlgorithm;
    public $VendorName;
    public $SubVendorName;
    public $Token;
    public $SerialNumber;
    public $CertificateStartDateInUTC;
    public $CertificateEndDateInUTC;
    public $PurchaseDateInUTC;
    public $PollDateInUTC;
    public $PreOrganizationId;
}

class order_approverlist_response extends baseresponse
{
    public $ApproverEmailList;
}

class order_agreement_response extends baseresponse
{
    public $Agreement;
}

class user_subuser_response extends baseresponse
{
    public $PartnerCode;
    public $CustomPartnerCode;
    public $AuthenticationToken;
    public $PartnerEmail;
    public $isEnabled;
    
    
}

class order_download_response extends baseresponse
{
    public $PartnerOrderID;
    public $CertificateStatus;
    public $ValidationStatus;
    public $Certificates;
    public $CertificateStartDate;
    public $CertificateEndDate;
    public $CertificateStartDateInUTC;
    public $CertificateEndDateInUTC;
}

class order_download_zip_response extends baseresponse
{
    public $PartnerOrderID;
    public $CertificateStatus;
    public $ValidationStatus;
    public $Zip;
    public $pkcs7zip;
    public $CertificateStartDate;
    public $CertificateEndDate;
    public $CertificateStartDateInUTC;
    public $CertificateEndDateInUTC;
}

class order_query_response extends baseresponse
{
    public function __construct()
    {
        $this->OrderStatus = new orderStatus();
        $this->AdminContact = new contact();
        $this->TechnicalContact = new contact();
        parent::__construct();
    }
    public $PartnerOrderID;
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $VendorOrderID;
    public $RefundRequestID;
    public $isRefundApproved;
    public $TinyOrderLink;
    public $OrderStatus;
    public $OrderAmount;
    public $PurchaseDate;
    public $CertificateStartDate;
    public $CertificateEndDate;
    public $CommonName;
    public $DNSNames;
    public $SANCount;
    public $ServerCount;
    public $Validity;
    public $Organization;
    public $OrganizationalUnit;
    public $State;
    public $Country;
    public $Locality;
    public $OrganizationPhone;
    public $OrganizationAddress;
    public $OrganizationPostalcode;
    public $DUNS;
    public $WebServerType;
    public $ApproverEmail;
    public $ProductName;
    public $AdminContact;
    public $TechnicalContact;
    public $ReissueSuccessCode;
    public $AuthFileName;
    public $AuthFileContent;
    public $PollStatus;
    public $PollDate;
    public $CustomerLoginName;
    public $CustomerPassword;
    public $CustomerID;
    public $TokenID;
    public $TokenCode;
    public $SiteSealurl;
    public $CNAMEAuthName;
    public $CNAMEAuthValue;
    public $SignatureEncryptionAlgorithm;
    public $SignatureHashAlgorithm;
    public $VendorName;
    public $SubVendorName;
    public $Token;
    public $SerialNumber;
    public $CertificateStartDateInUTC;
    public $CertificateEndDateInUTC;
    public $PurchaseDateInUTC;
    public $PollDateInUTC;
}

class order_modified_summary_response extends baseresponse{
    public $Orders;
    public $TotalOrders;
}

class order_pmr_response extends baseresponse{
    public $TheSSLStoreOrderID;
    public $PMRStatus;
    public $ExpediteDate;
}

class user_newuser_response extends baseresponse
{
    public $PartnerCode;
    public $isEnabled;
}

class user_account_detail_response extends baseresponse
{
    public $PartnerCode;
    public $CompanyName;
    public $FullName;
    public $Email;
    public $AlternateEmail;
    public $AccountType;
    public $AccountBalance;
    public $CurrentPlan;
    public $AllowCredit;
    public $Address;
    public $City;
    public $Country;
    public $Phone;
    public $State;
    public $Zip;
}

class user_query_response extends baseresponse
{
    public $PartnerCode;
    public $CustomePartnerCode;
    public $AuthenticationToken;
    public $PartnerEmail;
    public $isEnabled;
}
class order_vulnerabilityscanrequest_response extends baseresponse
{
    public $VulnerabilityScanDetails;
}
class order_replacement_response extends baseresponse
{
    /*public $PartnerOrderID;
    public $TheSSLStoreOrderID;
    public $VendorOrderID;
    public $OrderStatus;
    public $OrderAmount;
    public $CertificateStartDate;
    public $CertificateEndDate;
    public $CommonName;
    public $DNSNames;
    public $State;
    public $Country;
    public $Locality;
    public $Organization;
    public $OrganizationalUnit;
    public $WebServerType;
    public $ReissueSuccessCode;
	public $SiteSealurl;*/
}
class csr_download_response extends baseresponse{
    public $CSR;
}

class cwatch_order_response extends baseresponse{

    public function __construct()
    {
        $this->OrderStatus = new orderStatus();
        $this->AdminContact = new cwatch_admin_contact();
        parent::__construct();
    }
    public $PartnerOrderID;
    public $CustomOrderID;
    public $TheSSLStoreOrderID;
    public $VendorOrderID;
    public $RefundRequestID;
    public $isRefundApproved;
    public $TinyOrderLink;
    public $OrderStatus;
    public $OrderAmount;
    public $PurchaseDate;
    public $LicenseStartDate;
    public $LicenseEndDate;
    public $DomainName;
    public $Validity;
    public $ApproverEmail;
    public $ProductName;
    public $ProductCode;
    public $AdminContact;
    public $Token;
    public $LicenseKey;
}

class digicert_create_organization_response extends baseresponse{
    public $VendorOrganizationId;
    public $VendorApproversContactId;
    public $OrganizationId;
    public $ValidationsTypes;
}

class digicert_get_organization_response extends baseresponse{

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
    public $VendorOrganizationId;
    public $OrganizationId;
    public $VendorOrgContactId;
    public $VendorApproversContactId;
    public $Status;
    public $isActive;
}

class digicert_set_approver_method_response extends baseresponse{

    public function __construct(){
        $this->dcvDetails = new digicert_dcv_details();
        parent::__construct();
    }
    public $DomainName;
    public $preOrganizationId;
    public $TheSSLStoreOrderID;
    public $domain_id;
    public $domain_status;
    public $ValidationsTypes;
    public $date_created;
    public $dcvDetails;
}

class digicert_get_domain_resonse extends baseresponse{
    public function __construct(){
        $this->dcvDetails = new digicert_dcv_details();
        parent::__construct();
    }
    public $DomainName;
    public $domain_status;
    public $date_created;
    public $domain_isactive;
    public $TheSSLStoreOrderID;
    public $OrganizationDetails;
    public $dcvDetails;
    public $validations;
}