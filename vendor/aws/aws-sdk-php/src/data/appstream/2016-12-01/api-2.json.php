<?php
// This file was auto-generated from sdk-root/src/data/appstream/2016-12-01/api-2.json
return [ 'version' => '2.0', 'metadata' => [ 'apiVersion' => '2016-12-01', 'endpointPrefix' => 'appstream2', 'jsonVersion' => '1.1', 'protocol' => 'json', 'serviceFullName' => 'Amazon AppStream', 'signatureVersion' => 'v4', 'signingName' => 'appstream', 'targetPrefix' => 'PhotonAdminProxyService', 'uid' => 'appstream-2016-12-01', ], 'operations' => [ 'AssociateFleet' => [ 'name' => 'AssociateFleet', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'AssociateFleetRequest', ], 'output' => [ 'shape' => 'AssociateFleetResult', ], 'errors' => [ [ 'shape' => 'LimitExceededException', ], [ 'shape' => 'ResourceNotFoundException', ], ], ], 'CreateFleet' => [ 'name' => 'CreateFleet', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'CreateFleetRequest', ], 'output' => [ 'shape' => 'CreateFleetResult', ], 'errors' => [ [ 'shape' => 'ResourceAlreadyExistsException', ], [ 'shape' => 'ResourceNotAvailableException', ], [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'LimitExceededException', ], [ 'shape' => 'InvalidRoleException', ], ], ], 'CreateStack' => [ 'name' => 'CreateStack', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'CreateStackRequest', ], 'output' => [ 'shape' => 'CreateStackResult', ], 'errors' => [ [ 'shape' => 'LimitExceededException', ], [ 'shape' => 'ResourceAlreadyExistsException', ], ], ], 'CreateStreamingURL' => [ 'name' => 'CreateStreamingURL', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'CreateStreamingURLRequest', ], 'output' => [ 'shape' => 'CreateStreamingURLResult', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'ResourceNotAvailableException', ], [ 'shape' => 'OperationNotPermittedException', ], ], ], 'DeleteFleet' => [ 'name' => 'DeleteFleet', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DeleteFleetRequest', ], 'output' => [ 'shape' => 'DeleteFleetResult', ], 'errors' => [ [ 'shape' => 'ResourceInUseException', ], [ 'shape' => 'ResourceNotFoundException', ], ], ], 'DeleteStack' => [ 'name' => 'DeleteStack', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DeleteStackRequest', ], 'output' => [ 'shape' => 'DeleteStackResult', ], 'errors' => [ [ 'shape' => 'ResourceInUseException', ], [ 'shape' => 'ResourceNotFoundException', ], ], ], 'DescribeFleets' => [ 'name' => 'DescribeFleets', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeFleetsRequest', ], 'output' => [ 'shape' => 'DescribeFleetsResult', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], ], ], 'DescribeImages' => [ 'name' => 'DescribeImages', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeImagesRequest', ], 'output' => [ 'shape' => 'DescribeImagesResult', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], ], ], 'DescribeSessions' => [ 'name' => 'DescribeSessions', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeSessionsRequest', ], 'output' => [ 'shape' => 'DescribeSessionsResult', ], ], 'DescribeStacks' => [ 'name' => 'DescribeStacks', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeStacksRequest', ], 'output' => [ 'shape' => 'DescribeStacksResult', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], ], ], 'DisassociateFleet' => [ 'name' => 'DisassociateFleet', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DisassociateFleetRequest', ], 'output' => [ 'shape' => 'DisassociateFleetResult', ], 'errors' => [ [ 'shape' => 'ResourceInUseException', ], [ 'shape' => 'ResourceNotFoundException', ], ], ], 'ExpireSession' => [ 'name' => 'ExpireSession', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'ExpireSessionRequest', ], 'output' => [ 'shape' => 'ExpireSessionResult', ], ], 'ListAssociatedFleets' => [ 'name' => 'ListAssociatedFleets', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'ListAssociatedFleetsRequest', ], 'output' => [ 'shape' => 'ListAssociatedFleetsResult', ], ], 'ListAssociatedStacks' => [ 'name' => 'ListAssociatedStacks', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'ListAssociatedStacksRequest', ], 'output' => [ 'shape' => 'ListAssociatedStacksResult', ], ], 'StartFleet' => [ 'name' => 'StartFleet', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'StartFleetRequest', ], 'output' => [ 'shape' => 'StartFleetResult', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'LimitExceededException', ], ], ], 'StopFleet' => [ 'name' => 'StopFleet', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'StopFleetRequest', ], 'output' => [ 'shape' => 'StopFleetResult', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], ], ], 'UpdateFleet' => [ 'name' => 'UpdateFleet', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'UpdateFleetRequest', ], 'output' => [ 'shape' => 'UpdateFleetResult', ], 'errors' => [ [ 'shape' => 'ResourceInUseException', ], [ 'shape' => 'LimitExceededException', ], [ 'shape' => 'InvalidRoleException', ], [ 'shape' => 'ResourceNotFoundException', ], ], ], 'UpdateStack' => [ 'name' => 'UpdateStack', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'UpdateStackRequest', ], 'output' => [ 'shape' => 'UpdateStackResult', ], 'errors' => [ [ 'shape' => 'ResourceNotFoundException', ], [ 'shape' => 'ResourceInUseException', ], ], ], ], 'shapes' => [ 'Application' => [ 'type' => 'structure', 'members' => [ 'Name' => [ 'shape' => 'String', ], 'DisplayName' => [ 'shape' => 'String', ], 'IconURL' => [ 'shape' => 'String', ], 'LaunchPath' => [ 'shape' => 'String', ], 'LaunchParameters' => [ 'shape' => 'String', ], 'Enabled' => [ 'shape' => 'Boolean', ], 'Metadata' => [ 'shape' => 'Metadata', ], ], ], 'Applications' => [ 'type' => 'list', 'member' => [ 'shape' => 'Application', ], ], 'Arn' => [ 'type' => 'string', 'pattern' => '^arn:aws:[A-Za-z0-9][A-Za-z0-9_/.-]{0,62}:[A-Za-z0-9_/.-]{0,63}:[A-Za-z0-9_/.-]{0,63}:[A-Za-z0-9][A-Za-z0-9:_/+=,@.-]{0,1023}$', ], 'AssociateFleetRequest' => [ 'type' => 'structure', 'required' => [ 'FleetName', 'StackName', ], 'members' => [ 'FleetName' => [ 'shape' => 'String', ], 'StackName' => [ 'shape' => 'String', ], ], ], 'AssociateFleetResult' => [ 'type' => 'structure', 'members' => [], ], 'Boolean' => [ 'type' => 'boolean', ], 'ComputeCapacity' => [ 'type' => 'structure', 'required' => [ 'DesiredInstances', ], 'members' => [ 'DesiredInstances' => [ 'shape' => 'Integer', ], ], ], 'ComputeCapacityStatus' => [ 'type' => 'structure', 'required' => [ 'Desired', ], 'members' => [ 'Desired' => [ 'shape' => 'Integer', ], 'Running' => [ 'shape' => 'Integer', ], 'InUse' => [ 'shape' => 'Integer', ], 'Available' => [ 'shape' => 'Integer', ], ], ], 'CreateFleetRequest' => [ 'type' => 'structure', 'required' => [ 'Name', 'ImageName', 'InstanceType', 'ComputeCapacity', ], 'members' => [ 'Name' => [ 'shape' => 'Name', ], 'ImageName' => [ 'shape' => 'String', ], 'InstanceType' => [ 'shape' => 'String', ], 'ComputeCapacity' => [ 'shape' => 'ComputeCapacity', ], 'VpcConfig' => [ 'shape' => 'VpcConfig', ], 'MaxUserDurationInSeconds' => [ 'shape' => 'Integer', ], 'DisconnectTimeoutInSeconds' => [ 'shape' => 'Integer', ], 'Description' => [ 'shape' => 'Description', ], 'DisplayName' => [ 'shape' => 'DisplayName', ], ], ], 'CreateFleetResult' => [ 'type' => 'structure', 'members' => [ 'Fleet' => [ 'shape' => 'Fleet', ], ], ], 'CreateStackRequest' => [ 'type' => 'structure', 'required' => [ 'Name', ], 'members' => [ 'Name' => [ 'shape' => 'String', ], 'Description' => [ 'shape' => 'Description', ], 'DisplayName' => [ 'shape' => 'DisplayName', ], ], ], 'CreateStackResult' => [ 'type' => 'structure', 'members' => [ 'Stack' => [ 'shape' => 'Stack', ], ], ], 'CreateStreamingURLRequest' => [ 'type' => 'structure', 'required' => [ 'StackName', 'FleetName', 'UserId', ], 'members' => [ 'StackName' => [ 'shape' => 'String', ], 'FleetName' => [ 'shape' => 'String', ], 'UserId' => [ 'shape' => 'UserId', ], 'ApplicationId' => [ 'shape' => 'String', ], 'Validity' => [ 'shape' => 'Long', ], 'SessionContext' => [ 'shape' => 'String', ], ], ], 'CreateStreamingURLResult' => [ 'type' => 'structure', 'members' => [ 'StreamingURL' => [ 'shape' => 'String', ], 'Expires' => [ 'shape' => 'Timestamp', ], ], ], 'DeleteFleetRequest' => [ 'type' => 'structure', 'required' => [ 'Name', ], 'members' => [ 'Name' => [ 'shape' => 'String', ], ], ], 'DeleteFleetResult' => [ 'type' => 'structure', 'members' => [], ], 'DeleteStackRequest' => [ 'type' => 'structure', 'required' => [ 'Name', ], 'members' => [ 'Name' => [ 'shape' => 'String', ], ], ], 'DeleteStackResult' => [ 'type' => 'structure', 'members' => [], ], 'DescribeFleetsRequest' => [ 'type' => 'structure', 'members' => [ 'Names' => [ 'shape' => 'StringList', ], 'NextToken' => [ 'shape' => 'String', ], ], ], 'DescribeFleetsResult' => [ 'type' => 'structure', 'members' => [ 'Fleets' => [ 'shape' => 'FleetList', ], 'NextToken' => [ 'shape' => 'String', ], ], ], 'DescribeImagesRequest' => [ 'type' => 'structure', 'members' => [ 'Names' => [ 'shape' => 'StringList', ], ], ], 'DescribeImagesResult' => [ 'type' => 'structure', 'members' => [ 'Images' => [ 'shape' => 'ImageList', ], ], ], 'DescribeSessionsRequest' => [ 'type' => 'structure', 'required' => [ 'StackName', 'FleetName', ], 'members' => [ 'StackName' => [ 'shape' => 'String', ], 'FleetName' => [ 'shape' => 'String', ], 'UserId' => [ 'shape' => 'UserId', ], 'NextToken' => [ 'shape' => 'String', ], 'Limit' => [ 'shape' => 'Integer', ], ], ], 'DescribeSessionsResult' => [ 'type' => 'structure', 'members' => [ 'Sessions' => [ 'shape' => 'SessionList', ], 'NextToken' => [ 'shape' => 'String', ], ], ], 'DescribeStacksRequest' => [ 'type' => 'structure', 'members' => [ 'Names' => [ 'shape' => 'StringList', ], 'NextToken' => [ 'shape' => 'String', ], ], ], 'DescribeStacksResult' => [ 'type' => 'structure', 'members' => [ 'Stacks' => [ 'shape' => 'StackList', ], 'NextToken' => [ 'shape' => 'String', ], ], ], 'Description' => [ 'type' => 'string', 'max' => 256, ], 'DisassociateFleetRequest' => [ 'type' => 'structure', 'required' => [ 'FleetName', 'StackName', ], 'members' => [ 'FleetName' => [ 'shape' => 'String', ], 'StackName' => [ 'shape' => 'String', ], ], ], 'DisassociateFleetResult' => [ 'type' => 'structure', 'members' => [], ], 'DisplayName' => [ 'type' => 'string', 'max' => 100, ], 'ErrorMessage' => [ 'type' => 'string', ], 'ExpireSessionRequest' => [ 'type' => 'structure', 'required' => [ 'SessionId', ], 'members' => [ 'SessionId' => [ 'shape' => 'String', ], ], ], 'ExpireSessionResult' => [ 'type' => 'structure', 'members' => [], ], 'Fleet' => [ 'type' => 'structure', 'required' => [ 'Arn', 'Name', 'ImageName', 'InstanceType', 'ComputeCapacityStatus', 'State', ], 'members' => [ 'Arn' => [ 'shape' => 'Arn', ], 'Name' => [ 'shape' => 'String', ], 'DisplayName' => [ 'shape' => 'String', ], 'Description' => [ 'shape' => 'String', ], 'ImageName' => [ 'shape' => 'String', ], 'InstanceType' => [ 'shape' => 'String', ], 'ComputeCapacityStatus' => [ 'shape' => 'ComputeCapacityStatus', ], 'MaxUserDurationInSeconds' => [ 'shape' => 'Integer', ], 'DisconnectTimeoutInSeconds' => [ 'shape' => 'Integer', ], 'State' => [ 'shape' => 'FleetState', ], 'VpcConfig' => [ 'shape' => 'VpcConfig', ], 'CreatedTime' => [ 'shape' => 'Timestamp', ], 'FleetErrors' => [ 'shape' => 'FleetErrors', ], ], ], 'FleetError' => [ 'type' => 'structure', 'members' => [ 'ErrorCode' => [ 'shape' => 'FleetErrorCode', ], 'ErrorMessage' => [ 'shape' => 'String', ], ], ], 'FleetErrorCode' => [ 'type' => 'string', 'enum' => [ 'IAM_SERVICE_ROLE_MISSING_ENI_DESCRIBE_ACTION', 'IAM_SERVICE_ROLE_MISSING_ENI_CREATE_ACTION', 'IAM_SERVICE_ROLE_MISSING_ENI_DELETE_ACTION', 'NETWORK_INTERFACE_LIMIT_EXCEEDED', 'INTERNAL_SERVICE_ERROR', 'IAM_SERVICE_ROLE_IS_MISSING', 'SUBNET_HAS_INSUFFICIENT_IP_ADDRESSES', 'IAM_SERVICE_ROLE_MISSING_DESCRIBE_SUBNET_ACTION', 'SUBNET_NOT_FOUND', 'IMAGE_NOT_FOUND', 'INVALID_SUBNET_CONFIGURATION', ], ], 'FleetErrors' => [ 'type' => 'list', 'member' => [ 'shape' => 'FleetError', ], ], 'FleetList' => [ 'type' => 'list', 'member' => [ 'shape' => 'Fleet', ], ], 'FleetState' => [ 'type' => 'string', 'enum' => [ 'STARTING', 'RUNNING', 'STOPPING', 'STOPPED', ], ], 'Image' => [ 'type' => 'structure', 'required' => [ 'Name', ], 'members' => [ 'Name' => [ 'shape' => 'String', ], 'Arn' => [ 'shape' => 'Arn', ], 'BaseImageArn' => [ 'shape' => 'Arn', ], 'DisplayName' => [ 'shape' => 'String', ], 'State' => [ 'shape' => 'ImageState', ], 'Visibility' => [ 'shape' => 'VisibilityType', ], 'Platform' => [ 'shape' => 'PlatformType', ], 'Description' => [ 'shape' => 'String', ], 'StateChangeReason' => [ 'shape' => 'ImageStateChangeReason', ], 'Applications' => [ 'shape' => 'Applications', ], 'CreatedTime' => [ 'shape' => 'Timestamp', ], ], ], 'ImageList' => [ 'type' => 'list', 'member' => [ 'shape' => 'Image', ], ], 'ImageState' => [ 'type' => 'string', 'enum' => [ 'PENDING', 'AVAILABLE', 'FAILED', 'DELETING', ], ], 'ImageStateChangeReason' => [ 'type' => 'structure', 'members' => [ 'Code' => [ 'shape' => 'ImageStateChangeReasonCode', ], 'Message' => [ 'shape' => 'String', ], ], ], 'ImageStateChangeReasonCode' => [ 'type' => 'string', 'enum' => [ 'INTERNAL_ERROR', 'IMAGE_BUILDER_NOT_AVAILABLE', ], ], 'Integer' => [ 'type' => 'integer', ], 'InvalidRoleException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'ErrorMessage', ], ], 'exception' => true, ], 'LimitExceededException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'ErrorMessage', ], ], 'exception' => true, ], 'ListAssociatedFleetsRequest' => [ 'type' => 'structure', 'required' => [ 'StackName', ], 'members' => [ 'StackName' => [ 'shape' => 'String', ], 'NextToken' => [ 'shape' => 'String', ], ], ], 'ListAssociatedFleetsResult' => [ 'type' => 'structure', 'members' => [ 'Names' => [ 'shape' => 'StringList', ], 'NextToken' => [ 'shape' => 'String', ], ], ], 'ListAssociatedStacksRequest' => [ 'type' => 'structure', 'required' => [ 'FleetName', ], 'members' => [ 'FleetName' => [ 'shape' => 'String', ], 'NextToken' => [ 'shape' => 'String', ], ], ], 'ListAssociatedStacksResult' => [ 'type' => 'structure', 'members' => [ 'Names' => [ 'shape' => 'StringList', ], 'NextToken' => [ 'shape' => 'String', ], ], ], 'Long' => [ 'type' => 'long', ], 'Metadata' => [ 'type' => 'map', 'key' => [ 'shape' => 'String', ], 'value' => [ 'shape' => 'String', ], ], 'Name' => [ 'type' => 'string', 'pattern' => '^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,100}$', ], 'OperationNotPermittedException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'ErrorMessage', ], ], 'exception' => true, ], 'PlatformType' => [ 'type' => 'string', 'enum' => [ 'WINDOWS', ], ], 'ResourceAlreadyExistsException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'ErrorMessage', ], ], 'exception' => true, ], 'ResourceInUseException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'ErrorMessage', ], ], 'exception' => true, ], 'ResourceNotAvailableException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'ErrorMessage', ], ], 'exception' => true, ], 'ResourceNotFoundException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'ErrorMessage', ], ], 'exception' => true, ], 'Session' => [ 'type' => 'structure', 'required' => [ 'Id', 'UserId', 'StackName', 'FleetName', 'State', ], 'members' => [ 'Id' => [ 'shape' => 'String', ], 'UserId' => [ 'shape' => 'UserId', ], 'StackName' => [ 'shape' => 'String', ], 'FleetName' => [ 'shape' => 'String', ], 'State' => [ 'shape' => 'SessionState', ], ], ], 'SessionList' => [ 'type' => 'list', 'member' => [ 'shape' => 'Session', ], ], 'SessionState' => [ 'type' => 'string', 'enum' => [ 'ACTIVE', 'PENDING', 'EXPIRED', ], ], 'Stack' => [ 'type' => 'structure', 'required' => [ 'Name', ], 'members' => [ 'Arn' => [ 'shape' => 'Arn', ], 'Name' => [ 'shape' => 'String', ], 'Description' => [ 'shape' => 'String', ], 'DisplayName' => [ 'shape' => 'String', ], 'CreatedTime' => [ 'shape' => 'Timestamp', ], ], ], 'StackList' => [ 'type' => 'list', 'member' => [ 'shape' => 'Stack', ], ], 'StartFleetRequest' => [ 'type' => 'structure', 'required' => [ 'Name', ], 'members' => [ 'Name' => [ 'shape' => 'String', ], ], ], 'StartFleetResult' => [ 'type' => 'structure', 'members' => [], ], 'StopFleetRequest' => [ 'type' => 'structure', 'required' => [ 'Name', ], 'members' => [ 'Name' => [ 'shape' => 'String', ], ], ], 'StopFleetResult' => [ 'type' => 'structure', 'members' => [], ], 'String' => [ 'type' => 'string', 'min' => 1, ], 'StringList' => [ 'type' => 'list', 'member' => [ 'shape' => 'String', ], ], 'SubnetIdList' => [ 'type' => 'list', 'member' => [ 'shape' => 'String', ], 'min' => 1, ], 'Timestamp' => [ 'type' => 'timestamp', ], 'UpdateFleetRequest' => [ 'type' => 'structure', 'required' => [ 'Name', ], 'members' => [ 'ImageName' => [ 'shape' => 'String', ], 'Name' => [ 'shape' => 'String', ], 'InstanceType' => [ 'shape' => 'String', ], 'ComputeCapacity' => [ 'shape' => 'ComputeCapacity', ], 'VpcConfig' => [ 'shape' => 'VpcConfig', ], 'MaxUserDurationInSeconds' => [ 'shape' => 'Integer', ], 'DisconnectTimeoutInSeconds' => [ 'shape' => 'Integer', ], 'DeleteVpcConfig' => [ 'shape' => 'Boolean', ], 'Description' => [ 'shape' => 'Description', ], 'DisplayName' => [ 'shape' => 'DisplayName', ], ], ], 'UpdateFleetResult' => [ 'type' => 'structure', 'members' => [ 'Fleet' => [ 'shape' => 'Fleet', ], ], ], 'UpdateStackRequest' => [ 'type' => 'structure', 'required' => [ 'Name', ], 'members' => [ 'DisplayName' => [ 'shape' => 'DisplayName', ], 'Description' => [ 'shape' => 'Description', ], 'Name' => [ 'shape' => 'String', ], ], ], 'UpdateStackResult' => [ 'type' => 'structure', 'members' => [ 'Stack' => [ 'shape' => 'Stack', ], ], ], 'UserId' => [ 'type' => 'string', 'max' => 32, 'min' => 2, ], 'VisibilityType' => [ 'type' => 'string', 'enum' => [ 'PUBLIC', 'PRIVATE', ], ], 'VpcConfig' => [ 'type' => 'structure', 'required' => [ 'SubnetIds', ], 'members' => [ 'SubnetIds' => [ 'shape' => 'SubnetIdList', ], ], ], ],];
