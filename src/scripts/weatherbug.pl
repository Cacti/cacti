#!/usr/bin/env perl

delete @ENV{qw(PATH)};
$ENV{PATH} = '/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:/usr/local/sbin';

$output = `bash -c 'wget --quiet -O - \"http:\/\/wisapidata.weatherbug.com\/WxDataISAPI\/WxDataISAPI.dll?Magic=10991&RegNum=3647055&ZipCode=17241&StationID=NWVLL&Units=0&Version=2.7&Fore=1&t=1015084854\/"'`;

$output =~ s/[^-0-9|\|.]*//gi;

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

print "current_temp:" . $weather[3] . " wind_speed:" . $weather[5] . " barometer:" . $weather[10] . " humidity:" .
	$weather[11] . " high_temp:" . $weather[12] . " low_temp:" . $weather[13] . " dew_point_temp:" .
	$weather[14] . " wind_chill_temp:" . $weather[15];
