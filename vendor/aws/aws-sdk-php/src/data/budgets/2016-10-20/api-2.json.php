<?php
// This file was auto-generated from sdk-root/src/data/budgets/2016-10-20/api-2.json
return [ 'version' => '2.0', 'metadata' => [ 'apiVersion' => '2016-10-20', 'endpointPrefix' => 'budgets', 'jsonVersion' => '1.1', 'protocol' => 'json', 'serviceAbbreviation' => 'AWSBudgets', 'serviceFullName' => 'AWS Budgets', 'signatureVersion' => 'v4', 'targetPrefix' => 'AWSBudgetServiceGateway', 'uid' => 'budgets-2016-10-20', ], 'operations' => [ 'CreateBudget' => [ 'name' => 'CreateBudget', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'CreateBudgetRequest', ], 'output' => [ 'shape' => 'CreateBudgetResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'CreationLimitExceededException', ], [ 'shape' => 'DuplicateRecordException', ], ], ], 'CreateNotification' => [ 'name' => 'CreateNotification', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'CreateNotificationRequest', ], 'output' => [ 'shape' => 'CreateNotificationResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotFoundException', ], [ 'shape' => 'CreationLimitExceededException', ], [ 'shape' => 'DuplicateRecordException', ], ], ], 'CreateSubscriber' => [ 'name' => 'CreateSubscriber', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'CreateSubscriberRequest', ], 'output' => [ 'shape' => 'CreateSubscriberResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'CreationLimitExceededException', ], [ 'shape' => 'DuplicateRecordException', ], ], ], 'DeleteBudget' => [ 'name' => 'DeleteBudget', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DeleteBudgetRequest', ], 'output' => [ 'shape' => 'DeleteBudgetResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotFoundException', ], ], ], 'DeleteNotification' => [ 'name' => 'DeleteNotification', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DeleteNotificationRequest', ], 'output' => [ 'shape' => 'DeleteNotificationResponse', ], 'errors' => [ [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'NotFoundException', ], ], ], 'DeleteSubscriber' => [ 'name' => 'DeleteSubscriber', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DeleteSubscriberRequest', ], 'output' => [ 'shape' => 'DeleteSubscriberResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotFoundException', ], ], ], 'DescribeBudget' => [ 'name' => 'DescribeBudget', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeBudgetRequest', ], 'output' => [ 'shape' => 'DescribeBudgetResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotFoundException', ], ], ], 'DescribeBudgets' => [ 'name' => 'DescribeBudgets', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeBudgetsRequest', ], 'output' => [ 'shape' => 'DescribeBudgetsResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotFoundException', ], [ 'shape' => 'InvalidNextTokenException', ], [ 'shape' => 'ExpiredNextTokenException', ], ], ], 'DescribeNotificationsForBudget' => [ 'name' => 'DescribeNotificationsForBudget', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeNotificationsForBudgetRequest', ], 'output' => [ 'shape' => 'DescribeNotificationsForBudgetResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotFoundException', ], [ 'shape' => 'InvalidNextTokenException', ], [ 'shape' => 'ExpiredNextTokenException', ], ], ], 'DescribeSubscribersForNotification' => [ 'name' => 'DescribeSubscribersForNotification', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'DescribeSubscribersForNotificationRequest', ], 'output' => [ 'shape' => 'DescribeSubscribersForNotificationResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'NotFoundException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'InvalidNextTokenException', ], [ 'shape' => 'ExpiredNextTokenException', ], ], ], 'UpdateBudget' => [ 'name' => 'UpdateBudget', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'UpdateBudgetRequest', ], 'output' => [ 'shape' => 'UpdateBudgetResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotFoundException', ], ], ], 'UpdateNotification' => [ 'name' => 'UpdateNotification', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'UpdateNotificationRequest', ], 'output' => [ 'shape' => 'UpdateNotificationResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotFoundException', ], ], ], 'UpdateSubscriber' => [ 'name' => 'UpdateSubscriber', 'http' => [ 'method' => 'POST', 'requestUri' => '/', ], 'input' => [ 'shape' => 'UpdateSubscriberRequest', ], 'output' => [ 'shape' => 'UpdateSubscriberResponse', ], 'errors' => [ [ 'shape' => 'InternalErrorException', ], [ 'shape' => 'InvalidParameterException', ], [ 'shape' => 'NotFoundException', ], ], ], ], 'shapes' => [ 'AccountId' => [ 'type' => 'string', 'max' => 12, 'min' => 12, ], 'Budget' => [ 'type' => 'structure', 'required' => [ 'BudgetName', 'BudgetLimit', 'CostTypes', 'TimeUnit', 'TimePeriod', 'BudgetType', ], 'members' => [ 'BudgetName' => [ 'shape' => 'BudgetName', ], 'BudgetLimit' => [ 'shape' => 'Spend', ], 'CostFilters' => [ 'shape' => 'CostFilters', ], 'CostTypes' => [ 'shape' => 'CostTypes', ], 'TimeUnit' => [ 'shape' => 'TimeUnit', ], 'TimePeriod' => [ 'shape' => 'TimePeriod', ], 'CalculatedSpend' => [ 'shape' => 'CalculatedSpend', ], 'BudgetType' => [ 'shape' => 'BudgetType', ], ], ], 'BudgetName' => [ 'type' => 'string', 'max' => 100, 'pattern' => '[^:]+', ], 'BudgetType' => [ 'type' => 'string', 'enum' => [ 'USAGE', 'COST', ], ], 'Budgets' => [ 'type' => 'list', 'member' => [ 'shape' => 'Budget', ], ], 'CalculatedSpend' => [ 'type' => 'structure', 'required' => [ 'ActualSpend', ], 'members' => [ 'ActualSpend' => [ 'shape' => 'Spend', ], 'ForecastedSpend' => [ 'shape' => 'Spend', ], ], ], 'ComparisonOperator' => [ 'type' => 'string', 'enum' => [ 'GREATER_THAN', 'LESS_THAN', 'EQUAL_TO', ], ], 'CostFilters' => [ 'type' => 'map', 'key' => [ 'shape' => 'GenericString', ], 'value' => [ 'shape' => 'DimensionValues', ], ], 'CostTypes' => [ 'type' => 'structure', 'required' => [ 'IncludeTax', 'IncludeSubscription', 'UseBlended', ], 'members' => [ 'IncludeTax' => [ 'shape' => 'GenericBoolean', ], 'IncludeSubscription' => [ 'shape' => 'GenericBoolean', ], 'UseBlended' => [ 'shape' => 'GenericBoolean', ], ], ], 'CreateBudgetRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'Budget', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'Budget' => [ 'shape' => 'Budget', ], 'NotificationsWithSubscribers' => [ 'shape' => 'NotificationWithSubscribersList', ], ], ], 'CreateBudgetResponse' => [ 'type' => 'structure', 'members' => [], ], 'CreateNotificationRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', 'Notification', 'Subscribers', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], 'Notification' => [ 'shape' => 'Notification', ], 'Subscribers' => [ 'shape' => 'Subscribers', ], ], ], 'CreateNotificationResponse' => [ 'type' => 'structure', 'members' => [], ], 'CreateSubscriberRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', 'Notification', 'Subscriber', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], 'Notification' => [ 'shape' => 'Notification', ], 'Subscriber' => [ 'shape' => 'Subscriber', ], ], ], 'CreateSubscriberResponse' => [ 'type' => 'structure', 'members' => [], ], 'CreationLimitExceededException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'errorMessage', ], ], 'exception' => true, ], 'DeleteBudgetRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], ], ], 'DeleteBudgetResponse' => [ 'type' => 'structure', 'members' => [], ], 'DeleteNotificationRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', 'Notification', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], 'Notification' => [ 'shape' => 'Notification', ], ], ], 'DeleteNotificationResponse' => [ 'type' => 'structure', 'members' => [], ], 'DeleteSubscriberRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', 'Notification', 'Subscriber', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], 'Notification' => [ 'shape' => 'Notification', ], 'Subscriber' => [ 'shape' => 'Subscriber', ], ], ], 'DeleteSubscriberResponse' => [ 'type' => 'structure', 'members' => [], ], 'DescribeBudgetRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], ], ], 'DescribeBudgetResponse' => [ 'type' => 'structure', 'members' => [ 'Budget' => [ 'shape' => 'Budget', ], ], ], 'DescribeBudgetsRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'MaxResults' => [ 'shape' => 'MaxResults', ], 'NextToken' => [ 'shape' => 'GenericString', ], ], ], 'DescribeBudgetsResponse' => [ 'type' => 'structure', 'members' => [ 'Budgets' => [ 'shape' => 'Budgets', ], 'NextToken' => [ 'shape' => 'GenericString', ], ], ], 'DescribeNotificationsForBudgetRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], 'MaxResults' => [ 'shape' => 'MaxResults', ], 'NextToken' => [ 'shape' => 'GenericString', ], ], ], 'DescribeNotificationsForBudgetResponse' => [ 'type' => 'structure', 'members' => [ 'Notifications' => [ 'shape' => 'Notifications', ], 'NextToken' => [ 'shape' => 'GenericString', ], ], ], 'DescribeSubscribersForNotificationRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', 'Notification', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], 'Notification' => [ 'shape' => 'Notification', ], 'MaxResults' => [ 'shape' => 'MaxResults', ], 'NextToken' => [ 'shape' => 'GenericString', ], ], ], 'DescribeSubscribersForNotificationResponse' => [ 'type' => 'structure', 'members' => [ 'Subscribers' => [ 'shape' => 'Subscribers', ], 'NextToken' => [ 'shape' => 'GenericString', ], ], ], 'DimensionValues' => [ 'type' => 'list', 'member' => [ 'shape' => 'GenericString', ], ], 'DuplicateRecordException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'errorMessage', ], ], 'exception' => true, ], 'ExpiredNextTokenException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'errorMessage', ], ], 'exception' => true, ], 'GenericBoolean' => [ 'type' => 'boolean', ], 'GenericString' => [ 'type' => 'string', ], 'GenericTimestamp' => [ 'type' => 'timestamp', ], 'InternalErrorException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'errorMessage', ], ], 'exception' => true, ], 'InvalidNextTokenException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'errorMessage', ], ], 'exception' => true, ], 'InvalidParameterException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'errorMessage', ], ], 'exception' => true, ], 'MaxResults' => [ 'type' => 'integer', 'box' => true, 'max' => 100, 'min' => 1, ], 'NotFoundException' => [ 'type' => 'structure', 'members' => [ 'Message' => [ 'shape' => 'errorMessage', ], ], 'exception' => true, ], 'Notification' => [ 'type' => 'structure', 'required' => [ 'NotificationType', 'ComparisonOperator', 'Threshold', ], 'members' => [ 'NotificationType' => [ 'shape' => 'NotificationType', ], 'ComparisonOperator' => [ 'shape' => 'ComparisonOperator', ], 'Threshold' => [ 'shape' => 'NotificationThreshold', ], ], ], 'NotificationThreshold' => [ 'type' => 'double', 'max' => 100, 'min' => 0.10000000000000001, ], 'NotificationType' => [ 'type' => 'string', 'enum' => [ 'ACTUAL', 'FORECASTED', ], ], 'NotificationWithSubscribers' => [ 'type' => 'structure', 'required' => [ 'Notification', 'Subscribers', ], 'members' => [ 'Notification' => [ 'shape' => 'Notification', ], 'Subscribers' => [ 'shape' => 'Subscribers', ], ], ], 'NotificationWithSubscribersList' => [ 'type' => 'list', 'member' => [ 'shape' => 'NotificationWithSubscribers', ], 'max' => 5, ], 'Notifications' => [ 'type' => 'list', 'member' => [ 'shape' => 'Notification', ], ], 'NumericValue' => [ 'type' => 'string', 'pattern' => '[0-9]+(\\.)?[0-9]*', ], 'Spend' => [ 'type' => 'structure', 'required' => [ 'Amount', 'Unit', ], 'members' => [ 'Amount' => [ 'shape' => 'NumericValue', ], 'Unit' => [ 'shape' => 'GenericString', ], ], ], 'Subscriber' => [ 'type' => 'structure', 'required' => [ 'SubscriptionType', 'Address', ], 'members' => [ 'SubscriptionType' => [ 'shape' => 'SubscriptionType', ], 'Address' => [ 'shape' => 'GenericString', ], ], ], 'Subscribers' => [ 'type' => 'list', 'member' => [ 'shape' => 'Subscriber', ], 'max' => 11, 'min' => 1, ], 'SubscriptionType' => [ 'type' => 'string', 'enum' => [ 'SNS', 'EMAIL', ], ], 'TimePeriod' => [ 'type' => 'structure', 'required' => [ 'Start', 'End', ], 'members' => [ 'Start' => [ 'shape' => 'GenericTimestamp', ], 'End' => [ 'shape' => 'GenericTimestamp', ], ], ], 'TimeUnit' => [ 'type' => 'string', 'enum' => [ 'MONTHLY', 'QUARTERLY', 'ANNUALLY', ], ], 'UpdateBudgetRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'NewBudget', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'NewBudget' => [ 'shape' => 'Budget', ], ], ], 'UpdateBudgetResponse' => [ 'type' => 'structure', 'members' => [], ], 'UpdateNotificationRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', 'OldNotification', 'NewNotification', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], 'OldNotification' => [ 'shape' => 'Notification', ], 'NewNotification' => [ 'shape' => 'Notification', ], ], ], 'UpdateNotificationResponse' => [ 'type' => 'structure', 'members' => [], ], 'UpdateSubscriberRequest' => [ 'type' => 'structure', 'required' => [ 'AccountId', 'BudgetName', 'Notification', 'OldSubscriber', 'NewSubscriber', ], 'members' => [ 'AccountId' => [ 'shape' => 'AccountId', ], 'BudgetName' => [ 'shape' => 'BudgetName', ], 'Notification' => [ 'shape' => 'Notification', ], 'OldSubscriber' => [ 'shape' => 'Subscriber', ], 'NewSubscriber' => [ 'shape' => 'Subscriber', ], ], ], 'UpdateSubscriberResponse' => [ 'type' => 'structure', 'members' => [], ], 'errorMessage' => [ 'type' => 'string', ], ],];
