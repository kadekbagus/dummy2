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
	protected $description = 'Install mysql stored procedure and function, list : (a)fnc_campaign_cost';

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
			
			default:
				$this->info("You can install (a)fnc_campaign_cost");
				break;
		}
	}


	protected function createFunctionCampaignCost($prefix){
		
		DB::unprepared("DROP FUNCTION IF EXISTS fnc_campaign_cost;
		CREATE FUNCTION fnc_campaign_cost(`i_campaign_id` CHAR(16), `i_campaign_type` VARCHAR(20), `i_start_date` DATETIME, `i_end_date` DATETIME, `i_mall_time_zone` VARCHAR(10)) RETURNS decimal(10,0)
		BEGIN

		    /*
		    *	Description:
		    *		This function intends calculate the total cost of the campaign id given in parameters
		    *		on the period defined by i_start_date and i_end_date. The daily cost boundaries (dates) are given for the campaign running period
		    *		e.g. from campaign begin_date to campaign end_date. Any dates outside campaign begin_date and end_date are not counted
		    *		even if i_start_date and i_end_date are outside the campaign running dates.
		    *		
		    *	Parameters:
		    *		i_campaign_id CHAR(16)		:	The campaign_id for which the daily cost needs to be returned. Coupon, promotion and news campaign_id are handled.
		    *		i_campaign_type VARCHAR(20)	:	The campaign type should be either: 'coupon', 'promotion' or 'news'
		    *		i_start_date DATETIME		:	The start date used to filter out the result set returned
		    *		i_end_date DATETIME			:	The end date used to filter our the result set returned
		    *		i_mall_time_zone VARCHAR(10):	The mall time zone used in the stored proc to work out campaign activation/deactivation and number of tenants per day  
		    *									 	in the mall time zone as created_at in orb_campaign_histories is stored in UTC
		    *	Returns:
		    *		l_campaign_cost				:	The total campaign cost during the period defined by i_start_date and i_end_date
		    *
		    *	History:
		    *	02/02/2016: [TTL] Initial version 
		    *	03/02/2016: [TTL] Fixed time zone issue found by Isaac
		 	*	04/02/2016: [TTL] Refactored a bit the code, now there is only one SELECT statement that can handle any campaign type as campaign begin_date and end_date
		    *					  are retrieved separately.
		    */
		    
		    /* 
		    * TODO:	TBD
			*/

			DECLARE l_comp_start_date DATETIME;
			DECLARE l_number_days INT DEFAULT 0;
			DECLARE l_campaign_cost DECIMAL(10,2) DEFAULT 0.0;

			# First, retrieve the date of the first change found in orb_campaign_histories table
			# This date is later used as a start date to work out in campaign_histories the campaign activation/deactivation
			# and the number of tenants linked to the campaign per day 
			# This date is put back in the mall time zone
			SELECT 
				MIN(CONVERT_TZ(och.created_at, '+00:00', i_mall_time_zone))
			INTO 
				l_comp_start_date
			FROM
				{$prefix}campaign_histories och
			WHERE
				och.campaign_id = i_campaign_id
				AND och.campaign_type COLLATE utf8_unicode_ci = i_campaign_type COLLATE utf8_unicode_ci;

			# Because in orb_sequence table, the sequence starts from 1, need to remove one hour not to miss the first 
			# update in campaign_histories table. Actually, the sequence in orb_sequence table should start at 0 to avoid doing this
			SET l_comp_start_date := DATE_SUB(l_comp_start_date, INTERVAL 1 HOUR);

			# Calculaing the number of days to evaluate
			SET l_number_days := (DATEDIFF(DATE_FORMAT(i_end_date, '%Y-%m-%d'), DATE_FORMAT(l_comp_start_date, '%Y-%m-%d')) + 1);
			 
			# Initialize variable used dynamically in the query below
			SET @preCampaign := i_campaign_id; 
			SET @preAction := '';
			SET @nbTenant :=  0;
			SET @beginDate := '';
			SET @endDate := '';
				
			# Handling coupon type 
			IF i_campaign_type = 'coupon' THEN

				SELECT 
					op.begin_date,
					op.end_date
				INTO 
					@beginDate, 
					@endDate
				FROM
					{$prefix}promotions op
				WHERE 
					op.promotion_id = i_campaign_id;

			# Handling promotion and news type
			ELSEIF (i_campaign_type = 'promotion') OR (i_campaign_type = 'news') THEN

				SELECT
					orn.begin_date,
					orn.end_date
				INTO
					@beginDate,
					@endDate
				FROM
					{$prefix}news orn
				WHERE
					orn.news_id = i_campaign_id;

			END IF;

			IF (i_campaign_type = 'promotion') OR (i_campaign_type = 'news') OR (i_campaign_type = 'coupon') THEN

				SELECT 
					SUM(daily_cost) AS total_cost
				INTO l_campaign_cost
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
								0.0) AS daily_cost,
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
											@preAction := p2.action_name COLLATE utf8_general_ci
										) AS campaign_status,
										IF( p4.number_tenant IS NULL,
											@nbTenant := @nbTenant,
											@nbTenant := p4.number_tenant
										) AS campaign_number_tenant,            
										@beginDate AS campaign_start_date,
										@endDate AS campaign_end_date
									FROM
										(SELECT 
											DATE_FORMAT(DATE_ADD(l_comp_start_date, INTERVAL sequence_number HOUR), '%Y-%m-%d %H:00:00') AS comp_date
										FROM
											{$prefix}sequence os
										WHERE
											os.sequence_number <= (l_number_days * 24)
										) AS p1
									LEFT JOIN
										(
											SELECT 
												och.campaign_id,
												och.campaign_history_action_id,
												ocha.action_name,
												och.campaign_external_value,
												DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', i_mall_time_zone), '%Y-%m-%d %H:00:00') AS history_created_date
											FROM 
												{$prefix}campaign_histories och
											LEFT JOIN
												{$prefix}campaign_history_actions ocha
											ON och.campaign_history_action_id = ocha.campaign_history_action_id
											WHERE 
												och.campaign_history_action_id IN ('KcCyuvkMAg-XeXqj','KcCyuvkMAg-XeXqk')
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
												DATE_FORMAT(CONVERT_TZ(och.created_at, '+00:00', i_mall_time_zone), '%Y-%m-%d %H:00:00') AS history_created_date
											FROM 
												(SELECT @nbTenant := 0) AS init_nbTenant,
												{$prefix}campaign_histories och
											LEFT JOIN
												{$prefix}campaign_history_actions ocha
											ON och.campaign_history_action_id = ocha.campaign_history_action_id
											WHERE
												och.campaign_history_action_id IN ('KcCyuvkMAg-XeXqh', 'KcCyuvkMAg-XeXqi')
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
								#AND campaign_status = 'activate'
							GROUP BY DATE_FORMAT(comp_date, '%Y-%m-%d')
						) AS mquery
				) AS s
				WHERE
					s.comp_date BETWEEN DATE_FORMAT(i_start_date, '%Y-%m-%d') AND DATE_FORMAT(i_end_date, '%Y-%m-%d');
			END IF;

			RETURN l_campaign_cost;
		END");

		$this->info("create new function - fnc_campaign_cost, success!");
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
