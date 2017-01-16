<?php
// This file was auto-generated from sdk-root/src/data/cognito-identity/2014-06-30/api-2.json
return [ 'version' => '2.0', 'metadata' => [ 'apiVersion' => '2014-06-30', 'endpointPrefix' => 'cognito-identity', 'jsonVersion' => '1.1', 'protocol' => 'json', 'serviceFullName' => 'Amazon Cognito Identity', 'signatureVersion' => 'v4', 'targetPrefix' => 'AWSCognitoIdentityService', 'uid' => 'cognito-identity-2014-06-30', ], 'operations' => [ 'CreateIdentityPool' => [ 'name' => 'CreateIdentityPool', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'CreateIdentityPoolInput', ], 'output' => [ 'shape' => 'IdentityPool', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'LimitExceededException', ], ], ], 'DeleteIdentities' => [ 'name' => 'DeleteIdentities', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DeleteIdentitiesInput', ], 'output' => [ 'shape' => 'DeleteIdentitiesResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'DeleteIdentityPool' => [ 'name' => 'DeleteIdentityPool', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DeleteIdentityPoolInput', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'DescribeIdentity' => [ 'name' => 'DescribeIdentity', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeIdentityInput', ], 'output' => [ 'shape' => 'IdentityDescription', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'DescribeIdentityPool' => [ 'name' => 'DescribeIdentityPool', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeIdentityPoolInput', ], 'output' => [ 'shape' => 'IdentityPool', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'GetCredentialsForIdentity' => [ 'name' => 'GetCredentialsForIdentity', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'GetCredentialsForIdentityInput', ], 'output' => [ 'shape' => 'GetCredentialsForIdentityResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InvalidIdentityPoolConfigurationException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'ExternalServiceException', ], ], ], 'GetId' => [ 'name' => 'GetId', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'GetIdInput', ], 'output' => [ 'shape' => 'GetIdResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'LimitExceededException', ], [ 'shape' => 'ExternalServiceException', ], ], ], 'GetIdentityPoolRoles' => [ 'name' => 'GetIdentityPoolRoles', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'GetIdentityPoolRolesInput', ], 'output' => [ 'shape' => 'GetIdentityPoolRolesResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'GetOpenIdToken' => [ 'name' => 'GetOpenIdToken', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'GetOpenIdTokenInput', ], 'output' => [ 'shape' => 'GetOpenIdTokenResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'ExternalServiceException', ], ], ], 'GetOpenIdTokenForDeveloperIdentity' => [ 'name' => 'GetOpenIdTokenForDeveloperIdentity', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'GetOpenIdTokenForDeveloperIdentityInput', ], 'output' => [ 'shape' => 'GetOpenIdTokenForDeveloperIdentityResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'DeveloperUserAlreadyRegisteredException', ], ], ], 'ListIdentities' => [ 'name' => 'ListIdentities', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'ListIdentitiesInput', ], 'output' => [ 'shape' => 'ListIdentitiesResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'ListIdentityPools' => [ 'name' => 'ListIdentityPools', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'ListIdentityPoolsInput', ], 'output' => [ 'shape' => 'ListIdentityPoolsResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'LookupDeveloperIdentity' => [ 'name' => 'LookupDeveloperIdentity', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'LookupDeveloperIdentityInput', ], 'output' => [ 'shape' => 'LookupDeveloperIdentityResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'MergeDeveloperIdentities' => [ 'name' => 'MergeDeveloperIdentities', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'MergeDeveloperIdentitiesInput', ], 'output' => [ 'shape' => 'MergeDeveloperIdentitiesResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'SetIdentityPoolRoles' => [ 'name' => 'SetIdentityPoolRoles', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'SetIdentityPoolRolesInput', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'ConcurrentModificationException', ], ], ], 'UnlinkDeveloperIdentity' => [ 'name' => 'UnlinkDeveloperIdentity', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'UnlinkDeveloperIdentityInput', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], ], ], 'UnlinkIdentity' => [ 'name' => 'UnlinkIdentity', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'UnlinkIdentityInput', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'ExternalServiceException', ], ], ], 'UpdateIdentityPool' => [ 'name' => 'UpdateIdentityPool', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'IdentityPool', ], 'output' => [ 'shape' => 'IdentityPool', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'NotAuthorizedException', ], [ 'shape' => 'ResourceConflictException', ], [ 'shape' => 'TooManyRequestsException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'ConcurrentModificationException', ], [ 'shape' => 'LimitExceededException', ], ], ], ], 'shapes' => [ 'ARNString' => [ 'type' => 'string', 'max' => 2048, 'min' => 20, ], 'AccessKeyString' => [ 'type' => 'string', ], 'AccountId' => [ 'type' => 'string', 'max' => 15, 'min' => 1, 'pattern' => '\\d+', ], 'AmbiguousRoleResolutionType' => [ 'type' => 'string', 'enum' => [ 'AuthenticatedRole', 'Deny', ], ], 'ClaimName' => [ 'type' => 'string', 'max' => 64, 'min' => 1, 'pattern' => '[\\p{L}\\p{M}\\p{S}\\p{N}\\p{P}]+', ], 'ClaimValue' => [ 'type' => 'string', 'max' => 128, 'min' => 1, ], 'CognitoIdentityProvider' => [ 'type' => 'structure', 'members' => [ 'ProviderName' => [ 'shape' => 'CognitoIdentityProviderName', ], 'ClientId' => [ 'shape' => 'CognitoIdentityProviderClientId', ], ], ], 'CognitoIdentityProviderClientId' => [ 'type' => 'string', 'max' => 128, 'min' => 1, 'pattern' => '[\\w_]+', ], 'CognitoIdentityProviderList' => [ 'type' => 'list', 'member' => [ 'shape' => 'CognitoIdentityProvider', ], ], 'CognitoIdentityProviderName' => [ 'type' => 'string', 'max' => 128, 'min' => 1, 'pattern' => '[\\w._:/-]+', ], 'ConcurrentModificationException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'CreateIdentityPoolInput' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolName', 'AllowUnauthenticatedIdentities', ], 'members' => [ 'IdentityPoolName' => [ 'shape' => 'IdentityPoolName', ], 'AllowUnauthenticatedIdentities' => [ 'shape' => 'IdentityPoolUnauthenticated', ], 'SupportedLoginProviders' => [ 'shape' => 'IdentityProviders', ], 'DeveloperProviderName' => [ 'shape' => 'DeveloperProviderName', ], 'OpenIdConnectProviderARNs' => [ 'shape' => 'OIDCProviderList', ], 'CognitoIdentityProviders' => [ 'shape' => 'CognitoIdentityProviderList', ], 'SamlProviderARNs' => [ 'shape' => 'SAMLProviderList', ], ], ], 'Credentials' => [ 'type' => 'structure', 'members' => [ 'AccessKeyId' => [ 'shape' => 'AccessKeyString', ], 'SecretKey' => [ 'shape' => 'SecretKeyString', ], 'SessionToken' => [ 'shape' => 'SessionTokenString', ], 'Expiration' => [ 'shape' => 'DateType', ], ], ], 'DateType' => [ 'type' => 'timestamp', ], 'DeleteIdentitiesInput' => [ 'type' => 'structure', 'required' => [ 'IdentityIdsToDelete', ], 'members' => [ 'IdentityIdsToDelete' => [ 'shape' => 'IdentityIdList', ], ], ], 'DeleteIdentitiesResponse' => [ 'type' => 'structure', 'members' => [ 'UnprocessedIdentityIds' => [ 'shape' => 'UnprocessedIdentityIdList', ], ], ], 'DeleteIdentityPoolInput' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolId', ], 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], ], ], 'DescribeIdentityInput' => [ 'type' => 'structure', 'required' => [ 'IdentityId', ], 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], ], ], 'DescribeIdentityPoolInput' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolId', ], 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], ], ], 'DeveloperProviderName' => [ 'type' => 'string', 'max' => 128, 'min' => 1, 'pattern' => '[\\w._-]+', ], 'DeveloperUserAlreadyRegisteredException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'DeveloperUserIdentifier' => [ 'type' => 'string', 'max' => 1024, 'min' => 1, ], 'DeveloperUserIdentifierList' => [ 'type' => 'list', 'member' => [ 'shape' => 'DeveloperUserIdentifier', ], ], 'ErrorCode' => [ 'type' => 'string', 'enum' => [ 'AccessDenied', 'InternalServerError', ], ], 'ExternalServiceException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'GetCredentialsForIdentityInput' => [ 'type' => 'structure', 'required' => [ 'IdentityId', ], 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'Logins' => [ 'shape' => 'LoginsMap', ], 'CustomRoleArn' => [ 'shape' => 'ARNString', ], ], ], 'GetCredentialsForIdentityResponse' => [ 'type' => 'structure', 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'Credentials' => [ 'shape' => 'Credentials', ], ], ], 'GetIdInput' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolId', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'Logins' => [ 'shape' => 'LoginsMap', ], ], ], 'GetIdResponse' => [ 'type' => 'structure', 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], ], ], 'GetIdentityPoolRolesInput' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolId', ], 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], ], ], 'GetIdentityPoolRolesResponse' => [ 'type' => 'structure', 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'Roles' => [ 'shape' => 'RolesMap', ], 'RoleMappings' => [ 'shape' => 'RoleMappingMap', ], ], ], 'GetOpenIdTokenForDeveloperIdentityInput' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolId', 'Logins', ], 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'IdentityId' => [ 'shape' => 'IdentityId', ], 'Logins' => [ 'shape' => 'LoginsMap', ], 'TokenDuration' => [ 'shape' => 'TokenDuration', ], ], ], 'GetOpenIdTokenForDeveloperIdentityResponse' => [ 'type' => 'structure', 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'Token' => [ 'shape' => 'OIDCToken', ], ], ], 'GetOpenIdTokenInput' => [ 'type' => 'structure', 'required' => [ 'IdentityId', ], 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'Logins' => [ 'shape' => 'LoginsMap', ], ], ], 'GetOpenIdTokenResponse' => [ 'type' => 'structure', 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'Token' => [ 'shape' => 'OIDCToken', ], ], ], 'HideDisabled' => [ 'type' => 'boolean', ], 'IdentitiesList' => [ 'type' => 'list', 'member' => [ 'shape' => 'IdentityDescription', ], ], 'IdentityDescription' => [ 'type' => 'structure', 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'Logins' => [ 'shape' => 'LoginsList', ], 'CreationDate' => [ 'shape' => 'DateType', ], 'LastModifiedDate' => [ 'shape' => 'DateType', ], ], ], 'IdentityId' => [ 'type' => 'string', 'max' => 55, 'min' => 1, 'pattern' => '[\\w-]+:[0-9a-f-]+', ], 'IdentityIdList' => [ 'type' => 'list', 'member' => [ 'shape' => 'IdentityId', ], 'max' => 60, 'min' => 1, ], 'IdentityPool' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolId', 'IdentityPoolName', 'AllowUnauthenticatedIdentities', ], 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'IdentityPoolName' => [ 'shape' => 'IdentityPoolName', ], 'AllowUnauthenticatedIdentities' => [ 'shape' => 'IdentityPoolUnauthenticated', ], 'SupportedLoginProviders' => [ 'shape' => 'IdentityProviders', ], 'DeveloperProviderName' => [ 'shape' => 'DeveloperProviderName', ], 'OpenIdConnectProviderARNs' => [ 'shape' => 'OIDCProviderList', ], 'CognitoIdentityProviders' => [ 'shape' => 'CognitoIdentityProviderList', ], 'SamlProviderARNs' => [ 'shape' => 'SAMLProviderList', ], ], ], 'IdentityPoolId' => [ 'type' => 'string', 'max' => 55, 'min' => 1, 'pattern' => '[\\w-]+:[0-9a-f-]+', ], 'IdentityPoolName' => [ 'type' => 'string', 'max' => 128, 'min' => 1, 'pattern' => '[\\w ]+', ], 'IdentityPoolShortDescription' => [ 'type' => 'structure', 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'IdentityPoolName' => [ 'shape' => 'IdentityPoolName', ], ], ], 'IdentityPoolUnauthenticated' => [ 'type' => 'boolean', ], 'IdentityPoolsList' => [ 'type' => 'list', 'member' => [ 'shape' => 'IdentityPoolShortDescription', ], ], 'IdentityProviderId' => [ 'type' => 'string', 'max' => 128, 'min' => 1, 'pattern' => '[\\w.;_/-]+', ], 'IdentityProviderName' => [ 'type' => 'string', 'max' => 128, 'min' => 1, ], 'IdentityProviderToken' => [ 'type' => 'string', 'max' => 50000, 'min' => 1, ], 'IdentityProviders' => [ 'type' => 'map', 'key' => [ 'shape' => 'IdentityProviderName', ], 'value' => [ 'shape' => 'IdentityProviderId', ], 'max' => 10, ], 'InternalErrorException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, 'fault' => true, ], 'InvalidIdentityPoolConfigurationException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'InvalidParameterException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'LimitExceededException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'ListIdentitiesInput' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolId', 'MaxResults', ], 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'MaxResults' => [ 'shape' => 'QueryLimit', ], 'NextToken' => [ 'shape' => 'PaginationKey', ], 'HideDisabled' => [ 'shape' => 'HideDisabled', ], ], ], 'ListIdentitiesResponse' => [ 'type' => 'structure', 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'Identities' => [ 'shape' => 'IdentitiesList', ], 'NextToken' => [ 'shape' => 'PaginationKey', ], ], ], 'ListIdentityPoolsInput' => [ 'type' => 'structure', 'required' => [ 'MaxResults', ], 'members' => [ 'MaxResults' => [ 'shape' => 'QueryLimit', ], 'NextToken' => [ 'shape' => 'PaginationKey', ], ], ], 'ListIdentityPoolsResponse' => [ 'type' => 'structure', 'members' => [ 'IdentityPools' => [ 'shape' => 'IdentityPoolsList', ], 'NextToken' => [ 'shape' => 'PaginationKey', ], ], ], 'LoginsList' => [ 'type' => 'list', 'member' => [ 'shape' => 'IdentityProviderName', ], ], 'LoginsMap' => [ 'type' => 'map', 'key' => [ 'shape' => 'IdentityProviderName', ], 'value' => [ 'shape' => 'IdentityProviderToken', ], 'max' => 10, ], 'LookupDeveloperIdentityInput' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolId', ], 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'IdentityId' => [ 'shape' => 'IdentityId', ], 'DeveloperUserIdentifier' => [ 'shape' => 'DeveloperUserIdentifier', ], 'MaxResults' => [ 'shape' => 'QueryLimit', ], 'NextToken' => [ 'shape' => 'PaginationKey', ], ], ], 'LookupDeveloperIdentityResponse' => [ 'type' => 'structure', 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'DeveloperUserIdentifierList' => [ 'shape' => 'DeveloperUserIdentifierList', ], 'NextToken' => [ 'shape' => 'PaginationKey', ], ], ], 'MappingRule' => [ 'type' => 'structure', 'required' => [ 'Claim', 'MatchType', 'Value', 'RoleARN', ], 'members' => [ 'Claim' => [ 'shape' => 'ClaimName', ], 'MatchType' => [ 'shape' => 'MappingRuleMatchType', ], 'Value' => [ 'shape' => 'ClaimValue', ], 'RoleARN' => [ 'shape' => 'ARNString', ], ], ], 'MappingRuleMatchType' => [ 'type' => 'string', 'enum' => [ 'Equals', 'Contains', 'StartsWith', 'NotEqual', ], ], 'MappingRulesList' => [ 'type' => 'list', 'member' => [ 'shape' => 'MappingRule', ], 'max' => 25, 'min' => 1, ], 'MergeDeveloperIdentitiesInput' => [ 'type' => 'structure', 'required' => [ 'SourceUserIdentifier', 'DestinationUserIdentifier', 'DeveloperProviderName', 'IdentityPoolId', ], 'members' => [ 'SourceUserIdentifier' => [ 'shape' => 'DeveloperUserIdentifier', ], 'DestinationUserIdentifier' => [ 'shape' => 'DeveloperUserIdentifier', ], 'DeveloperProviderName' => [ 'shape' => 'DeveloperProviderName', ], 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], ], ], 'MergeDeveloperIdentitiesResponse' => [ 'type' => 'structure', 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], ], ], 'NotAuthorizedException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'OIDCProviderList' => [ 'type' => 'list', 'member' => [ 'shape' => 'ARNString', ], ], 'OIDCToken' => [ 'type' => 'string', ], 'PaginationKey' => [ 'type' => 'string', 'min' => 1, 'pattern' => '[\\S]+', ], 'QueryLimit' => [ 'type' => 'integer', 'max' => 60, 'min' => 1, ], 'ResourceConflictException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'ResourceNotFoundException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'RoleMapping' => [ 'type' => 'structure', 'required' => [ 'Type', ], 'members' => [ 'Type' => [ 'shape' => 'RoleMappingType', ], 'AmbiguousRoleResolution' => [ 'shape' => 'AmbiguousRoleResolutionType', ], 'RulesConfiguration' => [ 'shape' => 'RulesConfigurationType', ], ], ], 'RoleMappingMap' => [ 'type' => 'map', 'key' => [ 'shape' => 'IdentityProviderName', ], 'value' => [ 'shape' => 'RoleMapping', ], 'max' => 10, ], 'RoleMappingType' => [ 'type' => 'string', 'enum' => [ 'Token', 'Rules', ], ], 'RoleType' => [ 'type' => 'string', 'pattern' => '(un)?authenticated', ], 'RolesMap' => [ 'type' => 'map', 'key' => [ 'shape' => 'RoleType', ], 'value' => [ 'shape' => 'ARNString', ], 'max' => 2, ], 'RulesConfigurationType' => [ 'type' => 'structure', 'required' => [ 'Rules', ], 'members' => [ 'Rules' => [ 'shape' => 'MappingRulesList', ], ], ], 'SAMLProviderList' => [ 'type' => 'list', 'member' => [ 'shape' => 'ARNString', ], ], 'SecretKeyString' => [ 'type' => 'string', ], 'SessionTokenString' => [ 'type' => 'string', ], 'SetIdentityPoolRolesInput' => [ 'type' => 'structure', 'required' => [ 'IdentityPoolId', 'Roles', ], 'members' => [ 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'Roles' => [ 'shape' => 'RolesMap', ], 'RoleMappings' => [ 'shape' => 'RoleMappingMap', ], ], ], 'String' => [ 'type' => 'string', ], 'TokenDuration' => [ 'type' => 'long', 'max' => 86400, 'min' => 1, ], 'TooManyRequestsException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'String', ], ], 'exception' => true, ], 'UnlinkDeveloperIdentityInput' => [ 'type' => 'structure', 'required' => [ 'IdentityId', 'IdentityPoolId', 'DeveloperProviderName', 'DeveloperUserIdentifier', ], 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'IdentityPoolId' => [ 'shape' => 'IdentityPoolId', ], 'DeveloperProviderName' => [ 'shape' => 'DeveloperProviderName', ], 'DeveloperUserIdentifier' => [ 'shape' => 'DeveloperUserIdentifier', ], ], ], 'UnlinkIdentityInput' => [ 'type' => 'structure', 'required' => [ 'IdentityId', 'Logins', 'LoginsToRemove', ], 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'Logins' => [ 'shape' => 'LoginsMap', ], 'LoginsToRemove' => [ 'shape' => 'LoginsList', ], ], ], 'UnprocessedIdentityId' => [ 'type' => 'structure', 'members' => [ 'IdentityId' => [ 'shape' => 'IdentityId', ], 'ErrorCode' => [ 'shape' => 'ErrorCode', ], ], ], 'UnprocessedIdentityIdList' => [ 'type' => 'list', 'member' => [ 'shape' => 'UnprocessedIdentityId', ], 'max' => 60, ], ],];
