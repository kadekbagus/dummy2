<?php
// This file was auto-generated from sdk-root/src/data/signer/2017-08-25/api-2.json
return [ 'version' => '2.0', 'metadata' => [ 'apiVersion' => '2017-08-25', 'endpointPrefix' => 'signer', 'jsonVersion' => '1.1', 'protocol' => 'rest-json', 'serviceAbbreviation' => 'signer', 'serviceFullName' => 'AWS Signer', 'serviceId' => 'signer', 'signatureVersion' => 'v4', 'signingName' => 'signer', 'uid' => 'signer-2017-08-25', ], 'operations' => [ 'CancelSigningProfile' => [ 'name' => 'CancelSigningProfile', 'http' => [ 'method' => 'DELETE', 'requestUri' => '/signing-profiles/{profileName}', ], 'input' => [ 'shape' => 'CancelSigningProfileRequest', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'AccessDeniedException', ], [ 'shape' => 'ThrottlingException', ], [ 'shape' => 'InternalServiceErrorException', ], ], ], 'DescribeSigningJob' => [ 'name' => 'DescribeSigningJob', 'http' => [ 'method' => 'GET', 'requestUri' => '/signing-jobs/{jobId}', ], 'input' => [ 'shape' => 'DescribeSigningJobRequest', ], 'output' => [ 'shape' => 'DescribeSigningJobResponse', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'AccessDeniedException', ], [ 'shape' => 'InternalServiceErrorException', ], ], ], 'GetSigningPlatform' => [ 'name' => 'GetSigningPlatform', 'http' => [ 'method' => 'GET', 'requestUri' => '/signing-platforms/{platformId}', ], 'input' => [ 'shape' => 'GetSigningPlatformRequest', ], 'output' => [ 'shape' => 'GetSigningPlatformResponse', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'AccessDeniedException', ], [ 'shape' => 'InternalServiceErrorException', ], ], ], 'GetSigningProfile' => [ 'name' => 'GetSigningProfile', 'http' => [ 'method' => 'GET', 'requestUri' => '/signing-profiles/{profileName}', ], 'input' => [ 'shape' => 'GetSigningProfileRequest', ], 'output' => [ 'shape' => 'GetSigningProfileResponse', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'AccessDeniedException', ], [ 'shape' => 'ThrottlingException', ], [ 'shape' => 'InternalServiceErrorException', ], ], ], 'ListSigningJobs' => [ 'name' => 'ListSigningJobs', 'http' => [ 'method' => 'GET', 'requestUri' => '/signing-jobs', ], 'input' => [ 'shape' => 'ListSigningJobsRequest', ], 'output' => [ 'shape' => 'ListSigningJobsResponse', ], 'errors' => [ [ 'shape' => 'ValidationException', ], [ 'shape' => 'AccessDeniedException', ], [ 'shape' => 'ThrottlingException', ], [ 'shape' => 'InternalServiceErrorException', ], ], ], 'ListSigningPlatforms' => [ 'name' => 'ListSigningPlatforms', 'http' => [ 'method' => 'GET', 'requestUri' => '/signing-platforms', ], 'input' => [ 'shape' => 'ListSigningPlatformsRequest', ], 'output' => [ 'shape' => 'ListSigningPlatformsResponse', ], 'errors' => [ [ 'shape' => 'ValidationException', ], [ 'shape' => 'AccessDeniedException', ], [ 'shape' => 'ThrottlingException', ], [ 'shape' => 'InternalServiceErrorException', ], ], ], 'ListSigningProfiles' => [ 'name' => 'ListSigningProfiles', 'http' => [ 'method' => 'GET', 'requestUri' => '/signing-profiles', ], 'input' => [ 'shape' => 'ListSigningProfilesRequest', ], 'output' => [ 'shape' => 'ListSigningProfilesResponse', ], 'errors' => [ [ 'shape' => 'AccessDeniedException', ], [ 'shape' => 'ThrottlingException', ], [ 'shape' => 'InternalServiceErrorException', ], ], ], 'PutSigningProfile' => [ 'name' => 'PutSigningProfile', 'http' => [ 'method' => 'PUT', 'requestUri' => '/signing-profiles/{profileName}', ], 'input' => [ 'shape' => 'PutSigningProfileRequest', ], 'output' => [ 'shape' => 'PutSigningProfileResponse', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'AccessDeniedException', ], [ 'shape' => 'ValidationException', ], [ 'shape' => 'ThrottlingException', ], [ 'shape' => 'InternalServiceErrorException', ], ], ], 'StartSigningJob' => [ 'name' => 'StartSigningJob', 'http' => [ 'method' => 'POST', 'requestUri' => '/signing-jobs', ], 'input' => [ 'shape' => 'StartSigningJobRequest', ], 'output' => [ 'shape' => 'StartSigningJobResponse', ], 'errors' => [ [ 'shape' => 'ValidationException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'AccessDeniedException', ], [ 'shape' => 'ThrottlingException', ], [ 'shape' => 'InternalServiceErrorException', ], ], ], ], 'shapes' => [ 'key' => [ 'type' => 'string', ], 'AccessDeniedException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'ErrorMessage', ], ], 'error' => [ 'httpStatusCode' => 403, ], 'exception' => true, ], 'BucketName' => [ 'type' => 'string', ], 'CancelSigningProfileRequest' => [ 'type' => 'structure', 'required' => [ 'profileName', ], 'members' => [ 'profileName' => [ 'shape' => 'ProfileName', 'location' => 'uri', 'locationName' => 'profileName', ], ], ], 'Category' => [ 'type' => 'string', 'enum' => [ 'AWSIoT', ], ], 'CertificateArn' => [ 'type' => 'string', ], 'ClientRequestToken' => [ 'type' => 'string', ], 'CompletedAt' => [ 'type' => 'timestamp', ], 'CreatedAt' => [ 'type' => 'timestamp', ], 'DescribeSigningJobRequest' => [ 'type' => 'structure', 'required' => [ 'jobId', ], 'members' => [ 'jobId' => [ 'shape' => 'JobId', 'location' => 'uri', 'locationName' => 'jobId', ], ], ], 'DescribeSigningJobResponse' => [ 'type' => 'structure', 'members' => [ 'jobId' => [ 'shape' => 'JobId', ], 'source' => [ 'shape' => 'Source', ], 'signingMaterial' => [ 'shape' => 'SigningMaterial', ], 'platformId' => [ 'shape' => 'PlatformId', ], 'profileName' => [ 'shape' => 'ProfileName', ], 'overrides' => [ 'shape' => 'SigningPlatformOverrides', ], 'signingParameters' => [ 'shape' => 'SigningParameters', ], 'createdAt' => [ 'shape' => 'CreatedAt', ], 'completedAt' => [ 'shape' => 'CompletedAt', ], 'requestedBy' => [ 'shape' => 'RequestedBy', ], 'status' => [ 'shape' => 'SigningStatus', ], 'statusReason' => [ 'shape' => 'StatusReason', ], 'signedObject' => [ 'shape' => 'SignedObject', ], ], ], 'Destination' => [ 'type' => 'structure', 'members' => [ 's3' => [ 'shape' => 'S3Destination', ], ], ], 'DisplayName' => [ 'type' => 'string', ], 'EncryptionAlgorithm' => [ 'type' => 'string', 'enum' => [ 'RSA', 'ECDSA', ], ], 'EncryptionAlgorithmOptions' => [ 'type' => 'structure', 'required' => [ 'allowedValues', 'defaultValue', ], 'members' => [ 'allowedValues' => [ 'shape' => 'EncryptionAlgorithms', ], 'defaultValue' => [ 'shape' => 'EncryptionAlgorithm', ], ], ], 'EncryptionAlgorithms' => [ 'type' => 'list', 'member' => [ 'shape' => 'EncryptionAlgorithm', ], ], 'ErrorMessage' => [ 'type' => 'string', ], 'GetSigningPlatformRequest' => [ 'type' => 'structure', 'required' => [ 'platformId', ], 'members' => [ 'platformId' => [ 'shape' => 'PlatformId', 'location' => 'uri', 'locationName' => 'platformId', ], ], ], 'GetSigningPlatformResponse' => [ 'type' => 'structure', 'members' => [ 'platformId' => [ 'shape' => 'PlatformId', ], 'displayName' => [ 'shape' => 'DisplayName', ], 'partner' => [ 'shape' => 'String', ], 'target' => [ 'shape' => 'String', ], 'category' => [ 'shape' => 'Category', ], 'signingConfiguration' => [ 'shape' => 'SigningConfiguration', ], 'signingImageFormat' => [ 'shape' => 'SigningImageFormat', ], 'maxSizeInMB' => [ 'shape' => 'MaxSizeInMB', ], ], ], 'GetSigningProfileRequest' => [ 'type' => 'structure', 'required' => [ 'profileName', ], 'members' => [ 'profileName' => [ 'shape' => 'ProfileName', 'location' => 'uri', 'locationName' => 'profileName', ], ], ], 'GetSigningProfileResponse' => [ 'type' => 'structure', 'members' => [ 'profileName' => [ 'shape' => 'ProfileName', ], 'signingMaterial' => [ 'shape' => 'SigningMaterial', ], 'platformId' => [ 'shape' => 'PlatformId', ], 'overrides' => [ 'shape' => 'SigningPlatformOverrides', ], 'signingParameters' => [ 'shape' => 'SigningParameters', ], 'status' => [ 'shape' => 'SigningProfileStatus', ], ], ], 'HashAlgorithm' => [ 'type' => 'string', 'enum' => [ 'SHA1', 'SHA256', ], ], 'HashAlgorithmOptions' => [ 'type' => 'structure', 'required' => [ 'allowedValues', 'defaultValue', ], 'members' => [ 'allowedValues' => [ 'shape' => 'HashAlgorithms', ], 'defaultValue' => [ 'shape' => 'HashAlgorithm', ], ], ], 'HashAlgorithms' => [ 'type' => 'list', 'member' => [ 'shape' => 'HashAlgorithm', ], ], 'ImageFormat' => [ 'type' => 'string', 'enum' => [ 'JSON', ], ], 'ImageFormats' => [ 'type' => 'list', 'member' => [ 'shape' => 'ImageFormat', ], ], 'InternalServiceErrorException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'ErrorMessage', ], ], 'error' => [ 'httpStatusCode' => 500, ], 'exception' => true, ], 'JobId' => [ 'type' => 'string', ], 'Key' => [ 'type' => 'string', ], 'ListSigningJobsRequest' => [ 'type' => 'structure', 'members' => [ 'status' => [ 'shape' => 'SigningStatus', 'location' => 'querystring', 'locationName' => 'status', ], 'platformId' => [ 'shape' => 'PlatformId', 'location' => 'querystring', 'locationName' => 'platformId', ], 'requestedBy' => [ 'shape' => 'RequestedBy', 'location' => 'querystring', 'locationName' => 'requestedBy', ], 'maxResults' => [ 'shape' => 'MaxResults', 'location' => 'querystring', 'locationName' => 'maxResults', ], 'nextToken' => [ 'shape' => 'NextToken', 'location' => 'querystring', 'locationName' => 'nextToken', ], ], ], 'ListSigningJobsResponse' => [ 'type' => 'structure', 'members' => [ 'jobs' => [ 'shape' => 'SigningJobs', ], 'nextToken' => [ 'shape' => 'NextToken', ], ], ], 'ListSigningPlatformsRequest' => [ 'type' => 'structure', 'members' => [ 'category' => [ 'shape' => 'String', 'location' => 'querystring', 'locationName' => 'category', ], 'partner' => [ 'shape' => 'String', 'location' => 'querystring', 'locationName' => 'partner', ], 'target' => [ 'shape' => 'String', 'location' => 'querystring', 'locationName' => 'target', ], 'maxResults' => [ 'shape' => 'MaxResults', 'location' => 'querystring', 'locationName' => 'maxResults', ], 'nextToken' => [ 'shape' => 'String', 'location' => 'querystring', 'locationName' => 'nextToken', ], ], ], 'ListSigningPlatformsResponse' => [ 'type' => 'structure', 'members' => [ 'platforms' => [ 'shape' => 'SigningPlatforms', ], 'nextToken' => [ 'shape' => 'String', ], ], ], 'ListSigningProfilesRequest' => [ 'type' => 'structure', 'members' => [ 'includeCanceled' => [ 'shape' => 'bool', 'location' => 'querystring', 'locationName' => 'includeCanceled', ], 'maxResults' => [ 'shape' => 'MaxResults', 'location' => 'querystring', 'locationName' => 'maxResults', ], 'nextToken' => [ 'shape' => 'NextToken', 'location' => 'querystring', 'locationName' => 'nextToken', ], ], ], 'ListSigningProfilesResponse' => [ 'type' => 'structure', 'members' => [ 'profiles' => [ 'shape' => 'SigningProfiles', ], 'nextToken' => [ 'shape' => 'NextToken', ], ], ], 'MaxResults' => [ 'type' => 'integer', 'box' => true, 'max' => 25, 'min' => 1, ], 'MaxSizeInMB' => [ 'type' => 'integer', ], 'NextToken' => [ 'type' => 'string', ], 'PlatformId' => [ 'type' => 'string', ], 'Prefix' => [ 'type' => 'string', ], 'ProfileName' => [ 'type' => 'string', 'max' => 20, 'min' => 2, 'pattern' => '^[a-zA-Z0-9_]{2,}', ], 'PutSigningProfileRequest' => [ 'type' => 'structure', 'required' => [ 'profileName', 'signingMaterial', 'platformId', ], 'members' => [ 'profileName' => [ 'shape' => 'ProfileName', 'location' => 'uri', 'locationName' => 'profileName', ], 'signingMaterial' => [ 'shape' => 'SigningMaterial', ], 'platformId' => [ 'shape' => 'PlatformId', ], 'overrides' => [ 'shape' => 'SigningPlatformOverrides', ], 'signingParameters' => [ 'shape' => 'SigningParameters', ], ], ], 'PutSigningProfileResponse' => [ 'type' => 'structure', 'members' => [ 'arn' => [ 'shape' => 'string', ], ], ], 'RequestedBy' => [ 'type' => 'string', ], 'ResourceNotFoundException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'ErrorMessage', ], ], 'error' => [ 'httpStatusCode' => 404, ], 'exception' => true, ], 'S3Destination' => [ 'type' => 'structure', 'members' => [ 'bucketName' => [ 'shape' => 'BucketName', ], 'prefix' => [ 'shape' => 'Prefix', ], ], ], 'S3SignedObject' => [ 'type' => 'structure', 'members' => [ 'bucketName' => [ 'shape' => 'BucketName', ], 'key' => [ 'shape' => 'key', ], ], ], 'S3Source' => [ 'type' => 'structure', 'required' => [ 'bucketName', 'key', 'version', ], 'members' => [ 'bucketName' => [ 'shape' => 'BucketName', ], 'key' => [ 'shape' => 'Key', ], 'version' => [ 'shape' => 'Version', ], ], ], 'SignedObject' => [ 'type' => 'structure', 'members' => [ 's3' => [ 'shape' => 'S3SignedObject', ], ], ], 'SigningConfiguration' => [ 'type' => 'structure', 'required' => [ 'encryptionAlgorithmOptions', 'hashAlgorithmOptions', ], 'members' => [ 'encryptionAlgorithmOptions' => [ 'shape' => 'EncryptionAlgorithmOptions', ], 'hashAlgorithmOptions' => [ 'shape' => 'HashAlgorithmOptions', ], ], ], 'SigningConfigurationOverrides' => [ 'type' => 'structure', 'members' => [ 'encryptionAlgorithm' => [ 'shape' => 'EncryptionAlgorithm', ], 'hashAlgorithm' => [ 'shape' => 'HashAlgorithm', ], ], ], 'SigningImageFormat' => [ 'type' => 'structure', 'required' => [ 'supportedFormats', 'defaultFormat', ], 'members' => [ 'supportedFormats' => [ 'shape' => 'ImageFormats', ], 'defaultFormat' => [ 'shape' => 'ImageFormat', ], ], ], 'SigningJob' => [ 'type' => 'structure', 'members' => [ 'jobId' => [ 'shape' => 'JobId', ], 'source' => [ 'shape' => 'Source', ], 'signedObject' => [ 'shape' => 'SignedObject', ], 'signingMaterial' => [ 'shape' => 'SigningMaterial', ], 'createdAt' => [ 'shape' => 'CreatedAt', ], 'status' => [ 'shape' => 'SigningStatus', ], ], ], 'SigningJobs' => [ 'type' => 'list', 'member' => [ 'shape' => 'SigningJob', ], ], 'SigningMaterial' => [ 'type' => 'structure', 'required' => [ 'certificateArn', ], 'members' => [ 'certificateArn' => [ 'shape' => 'CertificateArn', ], ], ], 'SigningParameterKey' => [ 'type' => 'string', ], 'SigningParameterValue' => [ 'type' => 'string', ], 'SigningParameters' => [ 'type' => 'map', 'key' => [ 'shape' => 'SigningParameterKey', ], 'value' => [ 'shape' => 'SigningParameterValue', ], ], 'SigningPlatform' => [ 'type' => 'structure', 'members' => [ 'platformId' => [ 'shape' => 'String', ], 'displayName' => [ 'shape' => 'String', ], 'partner' => [ 'shape' => 'String', ], 'target' => [ 'shape' => 'String', ], 'category' => [ 'shape' => 'Category', ], 'signingConfiguration' => [ 'shape' => 'SigningConfiguration', ], 'signingImageFormat' => [ 'shape' => 'SigningImageFormat', ], 'maxSizeInMB' => [ 'shape' => 'MaxSizeInMB', ], ], ], 'SigningPlatformOverrides' => [ 'type' => 'structure', 'members' => [ 'signingConfiguration' => [ 'shape' => 'SigningConfigurationOverrides', ], ], ], 'SigningPlatforms' => [ 'type' => 'list', 'member' => [ 'shape' => 'SigningPlatform', ], ], 'SigningProfile' => [ 'type' => 'structure', 'members' => [ 'profileName' => [ 'shape' => 'ProfileName', ], 'signingMaterial' => [ 'shape' => 'SigningMaterial', ], 'platformId' => [ 'shape' => 'PlatformId', ], 'signingParameters' => [ 'shape' => 'SigningParameters', ], 'status' => [ 'shape' => 'SigningProfileStatus', ], ], ], 'SigningProfileStatus' => [ 'type' => 'string', 'enum' => [ 'Active', 'Canceled', ], ], 'SigningProfiles' => [ 'type' => 'list', 'member' => [ 'shape' => 'SigningProfile', ], ], 'SigningStatus' => [ 'type' => 'string', 'enum' => [ 'InProgress', 'Failed', 'Succeeded', ], ], 'Source' => [ 'type' => 'structure', 'members' => [ 's3' => [ 'shape' => 'S3Source', ], ], ], 'StartSigningJobRequest' => [ 'type' => 'structure', 'required' => [ 'source', 'destination', 'clientRequestToken', ], 'members' => [ 'source' => [ 'shape' => 'Source', ], 'destination' => [ 'shape' => 'Destination', ], 'profileName' => [ 'shape' => 'ProfileName', ], 'clientRequestToken' => [ 'shape' => 'ClientRequestToken', 'idempotencyToken' => true, ], ], ], 'StartSigningJobResponse' => [ 'type' => 'structure', 'members' => [ 'jobId' => [ 'shape' => 'JobId', ], ], ], 'StatusReason' => [ 'type' => 'string', ], 'String' => [ 'type' => 'string', ], 'ThrottlingException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'ErrorMessage', ], ], 'error' => [ 'httpStatusCode' => 429, ], 'exception' => true, ], 'ValidationException' => [ 'type' => 'structure', 'members' => [ 'message' => [ 'shape' => 'ErrorMessage', ], ], 'error' => [ 'httpStatusCode' => 400, ], 'exception' => true, ], 'Version' => [ 'type' => 'string', ], 'bool' => [ 'type' => 'boolean', ], 'string' => [ 'type' => 'string', ], ],];
