<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MysqlStoredProcedure extends Command {

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'mysqlstoredproc:install';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Install mysql stored procedure and function, list : (a)fnc_campaign_cost (b)proc_campaign_detailed_cost';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function fire()
	{
		$name = $this->option('name');
		$prefix = DB::getTablePrefix();

		switch ($name) {
			
			case 'fnc_campaign_cost':
				$this->createFunctionCampaignCost($prefix);
				break;

			case 'proc_campaign_detailed_cost':
				$this->createCampaignDetailedCostProc();
				break;
			
			default:
				$this->info("You can install (a)fnc_campaign_cost (b)proc_campaign_detailed_cost");
				break;
		}
	}

	private function createCampaignDetailedCostProc()
	{
		// Drop if it exists
		DB::unprepared('DROP PROCEDURE IF EXISTS `prc_campaign_detailed_cost`');

		// Prepare it
		$procDDL = file_get_contents(app_path('database/procs/prc_campaign_detailed_cost.sql'));
		$procDDL = str_replace('{{PREFIX}}', DB::getTablePrefix(), $procDDL);

		// Create it
		DB::unprepared($procDDL);

		$this->info('Successfully created proc "prc_campaign_detailed_cost".');
	}


	protected function createFunctionCampaignCost($prefix)
	{	
		DB::unprepared("DROP FUNCTION IF EXISTS fnc_campaign_cost;
		CREATE FUNCTION fnc_campaign_cost(i_campaign_id CHAR(16), i_campaign_type VARCHAR(20), i_start_date DATETIME, i_end_date DATETIME, i_mall_time_zone VARCHAR(10)) RETURNS decimal(10,2)
		BEGIN

			DECLARE comp_start_date DATETIME;
			DECLARE number_days INT DEFAULT 0;
			DECLARE campaign_cost DECIMAL(10,2) DEFAULT 0.0;

			SELECT 
				MIN(CONVERT_TZ(och.created_at, '+00:00', i_mall_time_zone))
			INTO 
				comp_start_date
			FROM
				{$prefix}campaign_histories och
			WHERE
				och.campaign_id = i_campaign_id
				AND och.campaign_type COLLATE utf8_unicode_ci = i_campaign_type COLLATE utf8_unicode_ci;

			SET comp_start_date := DATE_SUB(comp_start_date, INTERVAL 1 HOUR);
			SET number_days := (DATEDIFF(DATE_FORMAT(i_end_date, '%Y-%m-%d'), DATE_FORMAT(comp_start_date, '%Y-%m-%d')) + 1);
			 
			IF i_campaign_type = 'coupon' THEN

				SELECT 
					SUM(daily_cost) AS total_cost
				INTO campaign_cost
				FROM
					(
						SELECT
							mquery.comp_date,
							mquery.campaign_id,
							mquery.campaign_status,
							mquery.campaign_number_tenant,
							mquery.base_price,
							IF(	mquery.campaign_status = 'activate',
								(mquery.campaign_number_tenant * mquery.base_price),
								0) AS daily_cost,
							mquery.campaign_start_date,
							mquery.campaign_end_date
						FROM
						(    
							SELECT 
								DATE_FORMAT(ppp.comp_date, '%Y-%m-%d') AS comp_date,
								ppp.campaign_id,
								ppp.campaign_status,
								MAX(ppp.campaign_number_tenant) AS campaign_number_tenant,
								ocp.base_price,
								ppp.campaign_start_date,
								ppp.campaign_end_date    
							FROM
								(        
									SELECT 
										p1.comp_date,
										IF( p2.campaign_id IS NULL,
											@preCampaign := @preCampaign,
											@preCampaign := p2.campaign_id) AS campaign_id,     
										IF(	p2.action_name IS NULL,
											@preAction := @preAction,
											@preAction := p2.action_name
										) AS campaign_status,
										IF( p4.number_tenant IS NULL,
											@nbTenant := @nbTenant,
											@nbTenant := p4.number_tenant
										) AS campaign_number_tenant,            
										IF( p2.begin_date IS NULL,
											@beginDate := @beginDate,
											@beginDate := p2.begin_date
											) AS campaign_start_date,
										IF( p2.end_date IS NULL,
											@endDate := @endDate,
											@endDate := p2.end_date
											) AS campaign_end_date
									FROM
										(SELECT 
											@preCampaign := NULL, 
											@preAction := NULL,
											@nbTenant :=  0,
											@beginDate := NULL,
											@endDate := NULL
										) AS p3,
										(SELECT 
											DATE_FORMAT(DATE_ADD(comp_start_date, INTERVAL sequence_number HOUR), '%Y-%m-%d %H:00:00') AS comp_date
										FROM
											{$prefix}sequence os
										WHERE
											os.sequence_number <= (number_days * 24)
										) AS p1
									LEFT JOIN
										(
											SELECT 
												och.campaign_id,
												och.campaign_history_action_id,
												ocha.action_name,
												och.campaign_external_value,
												DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', i_mall_time_zone), '%Y-%m-%d %H:00:00') AS history_created_date,
												orn.begin_date,
												orn.end_date
											FROM 
												{$prefix}campaign_histories och
											LEFT JOIN
												{$prefix}campaign_history_actions ocha
											ON och.campaign_history_action_id = ocha.campaign_history_action_id
											LEFT JOIN
												{$prefix}promotions orn 
											ON och.campaign_id = orn.promotion_id 
											WHERE 
												och.campaign_history_action_id IN	(	SELECT campaign_history_action_id 
																						FROM {$prefix}campaign_history_actions 
																						WHERE action_name IN ('activate', 'deactivate')
																					)
												AND och.campaign_type COLLATE utf8_unicode_ci = i_campaign_type COLLATE utf8_unicode_ci
												AND och.campaign_id =  i_campaign_id
											ORDER BY DATE_FORMAT(history_created_date, '%Y-%m-%d'), action_name
										) AS p2
									ON p1.comp_date = p2.history_created_date
									LEFT JOIN
										(
											SELECT 
												och.campaign_id,
												och.campaign_history_action_id,
												ocha.action_name,
												CASE ocha.action_name
													WHEN 'add_tenant' THEN @nbTenant := @nbTenant + 1
													WHEN 'delete_tenant' THEN @nbTenant := @nbTenant - 1
												END AS number_tenant,
												och.campaign_external_value,
												DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', i_mall_time_zone), '%Y-%m-%d %H:00:00') AS history_created_date,
												orn.begin_date,
												orn.end_date
											FROM 
												(SELECT @nbTenant := 0) AS init_nbTenant,
												{$prefix}campaign_histories och
											LEFT JOIN
												{$prefix}campaign_history_actions ocha
											ON och.campaign_history_action_id = ocha.campaign_history_action_id
											LEFT JOIN
												{$prefix}promotions orn 
											ON och.campaign_id = orn.promotion_id 
											WHERE
												och.campaign_history_action_id IN	(	SELECT campaign_history_action_id 
																						FROM {$prefix}campaign_history_actions 
																						WHERE action_name IN ('add_tenant', 'delete_tenant')
																					)
												AND och.campaign_type COLLATE utf8_unicode_ci = i_campaign_type COLLATE utf8_unicode_ci
												AND och.campaign_id =  i_campaign_id
											ORDER BY och.created_at ASC
										) AS p4
									ON p1.comp_date = p4.history_created_date
									HAVING
										campaign_id IS NOT NULL
									ORDER BY DATE_FORMAT(p1.comp_date, '%Y-%m-%d'), campaign_status 
								) AS ppp
							LEFT JOIN 
								{$prefix}campaign_price ocp
							ON ppp.campaign_id = ocp.campaign_id
							WHERE DATE_FORMAT(campaign_start_date, '%Y-%m-%d %H:00:00') <= comp_date
								AND DATE_FORMAT(campaign_end_date, '%Y-%m-%d %H:59:00') >= comp_date
							GROUP BY DATE_FORMAT(comp_date, '%Y-%m-%d')
						) AS mquery
				) AS s
				WHERE
					s.comp_date BETWEEN DATE_FORMAT(i_start_date, '%Y-%m-%d') AND DATE_FORMAT(i_end_date, '%Y-%m-%d');

			ELSEIF (i_campaign_type = 'promotion') OR (i_campaign_type = 'news') THEN
				
				SELECT 
					SUM(daily_cost) AS total_cost
				INTO campaign_cost
				FROM
					(
						SELECT
							mquery.comp_date,
							mquery.campaign_id,
							mquery.campaign_status,
							mquery.campaign_number_tenant,
							mquery.base_price,
							IF(	mquery.campaign_status = 'activate',
								(mquery.campaign_number_tenant * mquery.base_price),
								0) AS daily_cost,
							mquery.campaign_start_date,
							mquery.campaign_end_date
						FROM
						(    
							SELECT 
								DATE_FORMAT(ppp.comp_date, '%Y-%m-%d') AS comp_date,
								ppp.campaign_id,
								ppp.campaign_status,
								MAX(ppp.campaign_number_tenant) AS campaign_number_tenant,
								ocp.base_price,
								ppp.campaign_start_date,
								ppp.campaign_end_date    
							FROM
								(        
									SELECT 
										p1.comp_date,
										IF( p2.campaign_id IS NULL,
											@preCampaign := @preCampaign,
											@preCampaign := p2.campaign_id) AS campaign_id,     
										IF(	p2.action_name IS NULL,
											@preAction := @preAction,
											@preAction := p2.action_name
										) AS campaign_status,
										IF( p4.number_tenant IS NULL,
											@nbTenant := @nbTenant,
											@nbTenant := p4.number_tenant
										) AS campaign_number_tenant,            
										IF( p2.begin_date IS NULL,
											@beginDate := @beginDate,
											@beginDate := p2.begin_date
											) AS campaign_start_date,
										IF( p2.end_date IS NULL,
											@endDate := @endDate,
											@endDate := p2.end_date
											) AS campaign_end_date
									FROM
										(SELECT 
											@preCampaign := NULL, 
											@preAction := NULL,
											@nbTenant :=  0,
											@beginDate := NULL,
											@endDate := NULL
										) AS p3,
										(SELECT 
											DATE_FORMAT(DATE_ADD(comp_start_date, INTERVAL sequence_number HOUR), '%Y-%m-%d %H:00:00') AS comp_date
										FROM
											{$prefix}sequence os
										WHERE
											os.sequence_number <= (number_days * 24)
										) AS p1
									LEFT JOIN
										(
											SELECT 
												och.campaign_id,
												och.campaign_history_action_id,
												ocha.action_name,
												och.campaign_external_value,
												DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', i_mall_time_zone), '%Y-%m-%d %H:00:00') AS history_created_date,
												orn.begin_date,
												orn.end_date
											FROM 
												{$prefix}campaign_histories och
											LEFT JOIN
												{$prefix}campaign_history_actions ocha
											ON och.campaign_history_action_id = ocha.campaign_history_action_id
											LEFT JOIN
												{$prefix}news orn
											ON och.campaign_id = orn.news_id
											WHERE 
												och.campaign_history_action_id IN	(	SELECT campaign_history_action_id 
																						FROM {$prefix}campaign_history_actions 
																						WHERE action_name IN ('activate', 'deactivate')
																					)
												AND och.campaign_type COLLATE utf8_unicode_ci = i_campaign_type COLLATE utf8_unicode_ci
												AND och.campaign_id =  i_campaign_id
											ORDER BY DATE_FORMAT(history_created_date, '%Y-%m-%d'), action_name
										) AS p2
									ON p1.comp_date = p2.history_created_date
									LEFT JOIN
										(
											SELECT 
												och.campaign_id,
												och.campaign_history_action_id,
												ocha.action_name,
												CASE ocha.action_name
													WHEN 'add_tenant' THEN @nbTenant := @nbTenant + 1
													WHEN 'delete_tenant' THEN @nbTenant := @nbTenant - 1
												END AS number_tenant,
												och.campaign_external_value,
												DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', i_mall_time_zone), '%Y-%m-%d %H:00:00') AS history_created_date,
												orn.begin_date,
												orn.end_date
											FROM 
												(SELECT @nbTenant := 0) AS init_nbTenant,
												{$prefix}campaign_histories och
											LEFT JOIN
												{$prefix}campaign_history_actions ocha
											ON och.campaign_history_action_id = ocha.campaign_history_action_id
											LEFT JOIN
												{$prefix}news orn
											ON och.campaign_id = orn.news_id
											WHERE
												och.campaign_history_action_id IN	(	SELECT campaign_history_action_id 
																						FROM {$prefix}campaign_history_actions 
																						WHERE action_name IN ('add_tenant', 'delete_tenant')
																					)
												AND och.campaign_type COLLATE utf8_unicode_ci = i_campaign_type COLLATE utf8_unicode_ci
												AND och.campaign_id =  i_campaign_id
											ORDER BY och.created_at ASC
										) AS p4
									ON p1.comp_date = p4.history_created_date
									HAVING
										campaign_id IS NOT NULL
									ORDER BY DATE_FORMAT(p1.comp_date, '%Y-%m-%d'), campaign_status 
								) AS ppp
							LEFT JOIN 
								{$prefix}campaign_price ocp
							ON ppp.campaign_id = ocp.campaign_id
							WHERE DATE_FORMAT(campaign_start_date, '%Y-%m-%d %H:00:00') <= comp_date
								AND DATE_FORMAT(campaign_end_date, '%Y-%m-%d %H:59:00') >= comp_date
							GROUP BY DATE_FORMAT(comp_date, '%Y-%m-%d')
						) AS mquery
				) AS s
				WHERE
					s.comp_date BETWEEN DATE_FORMAT(i_start_date, '%Y-%m-%d') AND DATE_FORMAT(i_end_date, '%Y-%m-%d');

			ELSE
				SET campaign_cost := 0.0;
			END IF;

			RETURN campaign_cost;


		END");

		$this->info("Function (fnc_campaign_cost) successfully created");
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array();
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
            array('name', null, InputOption::VALUE_REQUIRED, 'Name of Stored Procedure or Function, read the description for name list'),
            
        );
	}

}
