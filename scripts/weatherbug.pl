#!/usr/bin/perl

$output = `/bin/bash -c 'wget --quiet -O - \"http:\/\/wisapidata.weatherbug.com\/WxDataISAPI\/WxDataISAPI.dll?Magic=10991&RegNum=3647055&ZipCode=17241&StationID=NWVLL&Units=0&Version=2.7&Fore=1&t=1015084854\/"'`;

$output =~ s/[%]|[°]|[n]|[s]|[r]|[f]//gi;

@weather = split(/\|/, $output);

# docs
# [0] - ID?
# [1] - Current Time
# [2] - Current Date
# [3] - Current Temperature
# [5] - Wind Speed (MPH)
# [7] - Gust Wind Speed (MPH)
# [10] - Barometer (Moisture)
# [11] - Humidity (%)
# [12] - High Tempurature
# [13] - Low Temperature
# [14] - Dew Point
# [15] - Wind Chill

print $weather[3] . ":" . $weather[5] . ":" . $weather[10] . ":" . 
	$weather[11] . ":" . $weather[12] . ":" . $weather[13] . ":" . 
	$weather[14] . ":" . $weather[15];

