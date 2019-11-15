<?php
namespace Aws\ManagedBlockchain;

use Aws\AwsClient;

/**
 * This client is used to interact with the **Amazon Managed Blockchain** service.
 * @method \Aws\Result createMember(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createMemberAsync(array $args = [])
 * @method \Aws\Result createNetwork(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createNetworkAsync(array $args = [])
 * @method \Aws\Result createNode(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createNodeAsync(array $args = [])
 * @method \Aws\Result createProposal(array $args = [])
 * @method \GuzzleHttp\Promise\Promise createProposalAsync(array $args = [])
 * @method \Aws\Result deleteMember(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteMemberAsync(array $args = [])
 * @method \Aws\Result deleteNode(array $args = [])
 * @method \GuzzleHttp\Promise\Promise deleteNodeAsync(array $args = [])
 * @method \Aws\Result getMember(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getMemberAsync(array $args = [])
 * @method \Aws\Result getNetwork(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getNetworkAsync(array $args = [])
 * @method \Aws\Result getNode(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getNodeAsync(array $args = [])
 * @method \Aws\Result getProposal(array $args = [])
 * @method \GuzzleHttp\Promise\Promise getProposalAsync(array $args = [])
 * @method \Aws\Result listInvitations(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listInvitationsAsync(array $args = [])
 * @method \Aws\Result listMembers(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listMembersAsync(array $args = [])
 * @method \Aws\Result listNetworks(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listNetworksAsync(array $args = [])
 * @method \Aws\Result listNodes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listNodesAsync(array $args = [])
 * @method \Aws\Result listProposalVotes(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listProposalVotesAsync(array $args = [])
 * @method \Aws\Result listProposals(array $args = [])
 * @method \GuzzleHttp\Promise\Promise listProposalsAsync(array $args = [])
 * @method \Aws\Result rejectInvitation(array $args = [])
 * @method \GuzzleHttp\Promise\Promise rejectInvitationAsync(array $args = [])
 * @method \Aws\Result voteOnProposal(array $args = [])
 * @method \GuzzleHttp\Promise\Promise voteOnProposalAsync(array $args = [])
 */
class ManagedBlockchainClient extends AwsClient {}
