<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2009 - 2010, Phoronix Media
	Copyright (C) 2009 - 2010, Michael Larabel
	pts_ResultFileTable.php: The result file table object

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class pts_ResultFileTable extends pts_Table
{
	public function __construct(&$result_file, $system_id_keys = null, $result_object_index = -1)
	{
		list($rows, $columns, $table_data) = self::result_file_to_result_table($result_file, $system_id_keys, $result_object_index);
		parent::__construct($rows, $columns, $table_data);
		$this->result_object_index = $result_object_index;

		// where to start the table values
		$this->longest_row_identifier = null;
		$longest_row_title_length = 0;
		foreach($this->rows as $result_test)
		{
			if(($len = strlen($result_test[0])) > $longest_row_title_length)
			{
				$this->longest_row_identifier = $result_test[0];
				$longest_row_title_length = $len;
			}
		}
	}
	protected static function result_file_to_result_table(&$result_file, &$system_id_keys = null, &$result_object_index = -1)
	{
		$result_table = array();
		$result_tests = array();
		$result_counter = 0;

		foreach($result_file->get_system_identifiers() as $sys_identifier)
		{
			$result_table[$sys_identifier] = null;
		}

		foreach($result_file->get_result_objects($result_object_index) as $result_object)
		{
			$result_tests[$result_counter][0] = $result_object->test_profile->get_title();
			$result_tests[$result_counter][1] = $result_object->get_arguments_description();

			if($result_object_index != -1)
			{
				if(is_array($result_object_index))
				{
					$result_tests[$result_counter][0] = $result_tests[$result_counter][1];
				}
				else
				{
					$result_tests[$result_counter][0] = "Results";
				}
				//$result_tests[$result_counter][0] .= ': ' . $result_tests[$result_counter][1];
			}

			switch($result_object->test_profile->get_display_format())
			{
				case "BAR_GRAPH":
					$best_value = 0;

					if(!defined("PHOROMATIC_TRACKER") && count($result_object->test_result_buffer->get_values()) > 1)
					{
						switch($result_object->test_profile->get_result_proportion())
						{
							case "HIB":
								$best_value = max($result_object->test_result_buffer->get_values());
								break;
							case "LIB":
								$best_value = min($result_object->test_result_buffer->get_values());
								break;
						}
					}

					$prev_value = 0;
					$prev_identifier = null;
					$prev_identifier_0 = null;

					$values_in_buffer = $result_object->test_result_buffer->get_values();
					sort($values_in_buffer);
					$min_value_in_buffer = $values_in_buffer[0];
					$max_value_in_buffer = $values_in_buffer[(count($values_in_buffer) - 1)];

					foreach($result_object->test_result_buffer->get_buffer_items() as $index => $buffer_item)
					{
						$identifier = $buffer_item->get_result_identifier();
						$value = $buffer_item->get_result_value();
						$raw_values = pts_strings::colon_explode($buffer_item->get_result_raw());
						$percent_std = pts_math::set_precision(pts_math::percent_standard_deviation($raw_values), 2);
						$std_error = pts_math::set_precision(pts_math::standard_error($raw_values), 2);
						$delta = 0;

						if(defined("PHOROMATIC_TRACKER"))
						{
							$identifier_r = pts_strings::colon_explode($identifier);

							if($identifier_r[0] == $prev_identifier_0 && $prev_value != 0)
							{
								$delta = pts_math::set_precision(abs(1 - ($value / $prev_value)), 4);

								if($delta > 0.02 && $delta > pts_math::standard_deviation($raw_values))
								{
									switch($result_object->test_profile->get_result_proportion())
									{
										case "HIB":
											if($value < $prev_value)
											{
												$delta = 0 - $delta;
											}
											break;
										case "LIB":
											if($value > $prev_value)
											{
												$delta = 0 - $delta;
											}
											break;
									}
								}
								else
								{
									$delta = 0;
								}
							}

							$prev_identifier_0 = $identifier_r[0];
							$highlight = false;
						}
						else
						{
							if(PTS_IS_CLIENT && $result_file->is_multi_way_comparison())
							{
								// TODO: make it work better for highlighting multiple winners in multi-way comparisons
								$highlight = false;

								if($index % 2 == 1 && $prev_value != 0)
								{
									switch($result_object->test_profile->get_result_proportion())
									{
										case "HIB":
											if($value > $prev_value)
											{
												$highlight = true;
											}
											else
											{
												$result_table[$prev_identifier][$result_counter]->set_highlight(true);
												$result_table[$prev_identifier][$result_counter]->set_delta(-1);
											}
											break;
										case "LIB":
											if($value < $prev_value)
											{
												$highlight = true;
											}
											else
											{
												$result_table[$prev_identifier][$result_counter]->set_highlight(true);
												$result_table[$prev_identifier][$result_counter]->set_delta(-1);
											}
											break;
									}
								}
							}
							else
							{
								$highlight = $best_value == $value;
							}

							if($min_value_in_buffer != $max_value_in_buffer)
							{
								switch($result_object->test_profile->get_result_proportion())
								{
									case "HIB":
										$delta = pts_math::set_precision($value / $min_value_in_buffer, 2);
										break;
									case "LIB":
										$delta = pts_math::set_precision(1 - ($value / $max_value_in_buffer) + 1, 2);
										break;
								}
							}
						}

						$result_table[$identifier][$result_counter] = new pts_table_value($value, $percent_std, $std_error, $delta, $highlight);
						$prev_identifier = $identifier;
						$prev_value = $value;
					}
					break;
				case "LINE_GRAPH":
					$result_tests[$result_counter][0] = $result_object->test_profile->get_title() . " (Avg)";
					$result_tests[$result_counter][1] = null;

					foreach($result_object->test_result_buffer->get_buffer_items() as $index => $buffer_item)
					{
						$identifier = $buffer_item->get_result_identifier();
						$values = pts_strings::comma_explode($buffer_item->get_result_value());
						$avg_value = pts_math::set_precision(array_sum($values) / count($values), 2);
						$result_table[$identifier][$result_counter] = new pts_table_value($avg_value);
					}
					break;
			}

			$result_counter++;
		}

		if($result_counter == 1)
		{
			// This should provide some additional information under normal modes
			$has_written_std = false;
			$has_written_diff = false;
			$has_written_error = false;

			foreach($result_table as $identifier => $info)
			{
				if(!isset($info[($result_counter - 1)]))
				{
					continue;
				}

				$std_percent = $info[($result_counter - 1)]->get_standard_deviation_percent();
				$std_error = $info[($result_counter - 1)]->get_standard_error();
				$delta = $info[($result_counter - 1)]->get_delta();

				if($delta != 0)
				{
					array_push($result_table[$identifier], new pts_table_value($delta . 'x'));
					$has_written_diff = true;
				}
				if($std_error != 0)
				{
					array_push($result_table[$identifier], new pts_table_value($std_error));
					$has_written_error = true;
				}
				if($std_percent != 0)
				{
					array_push($result_table[$identifier], new pts_table_value($std_percent . "%"));
					$has_written_std = true;
				}
			}

			if($has_written_diff)
			{
				array_push($result_tests, array("Difference", null));
			}
			if($has_written_error)
			{
				array_push($result_tests, array("Standard Error", null));
			}
			if($has_written_std)
			{
				array_push($result_tests, array("Standard Deviation", null));
			}
		}

		if(defined("PHOROMATIC_TRACKER"))
		{
			// Resort the results by SYSTEM, then date
			$systems_table = array();
			$sorted_table = array();

			foreach($result_table as $system_identifier => &$identifier_table)
			{
				$identifier = pts_strings::colon_explode($system_identifier);

				if(!isset($systems_table[$identifier[0]]))
				{
					$systems_table[$identifier[0]] = array();
				}

				$systems_table[$identifier[0]][$system_identifier] = $identifier_table;
			}

			$result_table = array();
			$result_systems = array();

			foreach($systems_table as &$group)
			{
				foreach($group as $identifier => $table)
				{
					$result_table[$identifier] = $table;

					$identifier = pts_strings::colon_explode($identifier);
					$show_id = isset($identifier[1]) ? $identifier[1] : $identifier[0];

					if($system_id_keys != null && ($s = array_search($identifier[0], $system_id_keys)) !== false)
					{
						$system_id = $s;
					}
					else
					{
						$system_id = null;
					}

					array_push($result_systems, array($show_id, $system_id));
				}
			}
		}
		else
		{
			$result_systems = array();

			foreach(array_keys($result_table) as $id)
			{
				array_push($result_systems, array($id, null));
			}
		}

		return array($result_tests, $result_systems, $result_table);
	}
}

?>
