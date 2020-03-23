<?php
namespace Aws\SageMaker;

use Aws\AwsClient;

/**
 * This client is used to interact with the **Amazon SageMaker Service** service.
 * @method \Aws\Result addTags(array $args = [])
 * @method \GuzzleHttp\Promise\Promise addTagsAsync(array $args = [])
 * @method \Aws\Result createAlgorithm(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createAlgorithmAsync(array $args = [])
 * @method \Aws\Result createCodeRepository(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createCodeRepositoryAsync(array $args = [])
 * @method \Aws\Result createCompilationJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createCompilationJobAsync(array $args = [])
 * @method \Aws\Result createEndpoint(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createEndpointAsync(array $args = [])
 * @method \Aws\Result createEndpointConfig(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createEndpointConfigAsync(array $args = [])
 * @method \Aws\Result createHyperParameterTuningJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createHyperParameterTuningJobAsync(array $args = [])
 * @method \Aws\Result createLabelingJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createLabelingJobAsync(array $args = [])
 * @method \Aws\Result createModel(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createModelAsync(array $args = [])
 * @method \Aws\Result createModelPackage(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createModelPackageAsync(array $args = [])
 * @method \Aws\Result createNotebookInstance(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createNotebookInstanceAsync(array $args = [])
 * @method \Aws\Result createNotebookInstanceLifecycleConfig(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createNotebookInstanceLifecycleConfigAsync(array $args = [])
 * @method \Aws\Result createPresignedNotebookInstanceUrl(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createPresignedNotebookInstanceUrlAsync(array $args = [])
 * @method \Aws\Result createTrainingJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createTrainingJobAsync(array $args = [])
 * @method \Aws\Result createTransformJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createTransformJobAsync(array $args = [])
 * @method \Aws\Result createWorkteam(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createWorkteamAsync(array $args = [])
 * @method \Aws\Result deleteAlgorithm(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteAlgorithmAsync(array $args = [])
 * @method \Aws\Result deleteCodeRepository(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteCodeRepositoryAsync(array $args = [])
 * @method \Aws\Result deleteEndpoint(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteEndpointAsync(array $args = [])
 * @method \Aws\Result deleteEndpointConfig(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteEndpointConfigAsync(array $args = [])
 * @method \Aws\Result deleteModel(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteModelAsync(array $args = [])
 * @method \Aws\Result deleteModelPackage(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteModelPackageAsync(array $args = [])
 * @method \Aws\Result deleteNotebookInstance(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteNotebookInstanceAsync(array $args = [])
 * @method \Aws\Result deleteNotebookInstanceLifecycleConfig(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteNotebookInstanceLifecycleConfigAsync(array $args = [])
 * @method \Aws\Result deleteTags(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteTagsAsync(array $args = [])
 * @method \Aws\Result deleteWorkteam(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteWorkteamAsync(array $args = [])
 * @method \Aws\Result describeAlgorithm(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeAlgorithmAsync(array $args = [])
 * @method \Aws\Result describeCodeRepository(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeCodeRepositoryAsync(array $args = [])
 * @method \Aws\Result describeCompilationJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeCompilationJobAsync(array $args = [])
 * @method \Aws\Result describeEndpoint(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeEndpointAsync(array $args = [])
 * @method \Aws\Result describeEndpointConfig(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeEndpointConfigAsync(array $args = [])
 * @method \Aws\Result describeHyperParameterTuningJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeHyperParameterTuningJobAsync(array $args = [])
 * @method \Aws\Result describeLabelingJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeLabelingJobAsync(array $args = [])
 * @method \Aws\Result describeModel(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeModelAsync(array $args = [])
 * @method \Aws\Result describeModelPackage(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeModelPackageAsync(array $args = [])
 * @method \Aws\Result describeNotebookInstance(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeNotebookInstanceAsync(array $args = [])
 * @method \Aws\Result describeNotebookInstanceLifecycleConfig(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeNotebookInstanceLifecycleConfigAsync(array $args = [])
 * @method \Aws\Result describeSubscribedWorkteam(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeSubscribedWorkteamAsync(array $args = [])
 * @method \Aws\Result describeTrainingJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeTrainingJobAsync(array $args = [])
 * @method \Aws\Result describeTransformJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeTransformJobAsync(array $args = [])
 * @method \Aws\Result describeWorkteam(array $args = [])
 * @method \GuzzleHttp\Promise\Promise describeWorkteamAsync(array $args = [])
 * @method \Aws\Result getSearchSuggestions(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getSearchSuggestionsAsync(array $args = [])
 * @method \Aws\Result listAlgorithms(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listAlgorithmsAsync(array $args = [])
 * @method \Aws\Result listCodeRepositories(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listCodeRepositoriesAsync(array $args = [])
 * @method \Aws\Result listCompilationJobs(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listCompilationJobsAsync(array $args = [])
 * @method \Aws\Result listEndpointConfigs(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listEndpointConfigsAsync(array $args = [])
 * @method \Aws\Result listEndpoints(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listEndpointsAsync(array $args = [])
 * @method \Aws\Result listHyperParameterTuningJobs(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listHyperParameterTuningJobsAsync(array $args = [])
 * @method \Aws\Result listLabelingJobs(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listLabelingJobsAsync(array $args = [])
 * @method \Aws\Result listLabelingJobsForWorkteam(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listLabelingJobsForWorkteamAsync(array $args = [])
 * @method \Aws\Result listModelPackages(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listModelPackagesAsync(array $args = [])
 * @method \Aws\Result listModels(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listModelsAsync(array $args = [])
 * @method \Aws\Result listNotebookInstanceLifecycleConfigs(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listNotebookInstanceLifecycleConfigsAsync(array $args = [])
 * @method \Aws\Result listNotebookInstances(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listNotebookInstancesAsync(array $args = [])
 * @method \Aws\Result listSubscribedWorkteams(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listSubscribedWorkteamsAsync(array $args = [])
 * @method \Aws\Result listTags(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listTagsAsync(array $args = [])
 * @method \Aws\Result listTrainingJobs(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listTrainingJobsAsync(array $args = [])
 * @method \Aws\Result listTrainingJobsForHyperParameterTuningJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listTrainingJobsForHyperParameterTuningJobAsync(array $args = [])
 * @method \Aws\Result listTransformJobs(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listTransformJobsAsync(array $args = [])
 * @method \Aws\Result listWorkteams(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listWorkteamsAsync(array $args = [])
 * @method \Aws\Result renderUiTemplate(array $args = [])
 * @method \GuzzleHttp\Promise\Promise renderUiTemplateAsync(array $args = [])
 * @method \Aws\Result search(array $args = [])
 * @method \GuzzleHttp\Promise\Promise searchAsync(array $args = [])
 * @method \Aws\Result startNotebookInstance(array $args = [])
 * @method \GuzzleHttp\Promise\Promise startNotebookInstanceAsync(array $args = [])
 * @method \Aws\Result stopCompilationJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise stopCompilationJobAsync(array $args = [])
 * @method \Aws\Result stopHyperParameterTuningJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise stopHyperParameterTuningJobAsync(array $args = [])
 * @method \Aws\Result stopLabelingJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise stopLabelingJobAsync(array $args = [])
 * @method \Aws\Result stopNotebookInstance(array $args = [])
 * @method \GuzzleHttp\Promise\Promise stopNotebookInstanceAsync(array $args = [])
 * @method \Aws\Result stopTrainingJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise stopTrainingJobAsync(array $args = [])
 * @method \Aws\Result stopTransformJob(array $args = [])
 * @method \GuzzleHttp\Promise\Promise stopTransformJobAsync(array $args = [])
 * @method \Aws\Result updateCodeRepository(array $args = [])
 * @method \GuzzleHttp\Promise\Promise updateCodeRepositoryAsync(array $args = [])
 * @method \Aws\Result updateEndpoint(array $args = [])
 * @method \GuzzleHttp\Promise\Promise updateEndpointAsync(array $args = [])
 * @method \Aws\Result updateEndpointWeightsAndCapacities(array $args = [])
 * @method \GuzzleHttp\Promise\Promise updateEndpointWeightsAndCapacitiesAsync(array $args = [])
 * @method \Aws\Result updateNotebookInstance(array $args = [])
 * @method \GuzzleHttp\Promise\Promise updateNotebookInstanceAsync(array $args = [])
 * @method \Aws\Result updateNotebookInstanceLifecycleConfig(array $args = [])
 * @method \GuzzleHttp\Promise\Promise updateNotebookInstanceLifecycleConfigAsync(array $args = [])
 * @method \Aws\Result updateWorkteam(array $args = [])
 * @method \GuzzleHttp\Promise\Promise updateWorkteamAsync(array $args = [])
 */
class SageMakerClient extends AwsClient {}
