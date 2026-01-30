#!/usr/bin/python
#
# Description: Script to retrieve data from a Motorola SB6140 Cable Modem
# Version 1.0
#
# Programmed by tpowers

from lxml import html
import requests
import sys

usage = "usage: %s [Downstream|Upstream|Stats] <hostname>" % (sys.argv[0])
downChannels = ['1', '2', '3', '4', '5', '6', '7', '8']
upChannels = ['5', '6', '7', '8']

if len(sys.argv) != 3:
	print "category and hostname or IP of the modem are required!"
	print usage
	sys.exit(1)

queryType = sys.argv[1]
if queryType not in ['Downstream', 'Upstream', 'Stats']:
	print usage
	sys.exit(1)

try:
	page = requests.get('http://%s/cmSignalData.htm' % (sys.argv[2]))
	tree = html.fromstring(page.text)
except Exception, e:
	print "Unable to retrieve data from modem!"
	print e
	sys.exit(1)

data = {}
for table in tree.xpath('//table/tbody'):
	catagory = None
	channels = None
	dataRow = False
	for row in table:
		haveLable = False
		label = None
		channelCount = 0
		for cell in row:
			if dataRow:
				# handle the actual data
				if haveLable:
					data[catagory][label][channels[channelCount]] = cell.text.strip()
					channelCount += 1
				else:
					label = cell.text.strip()
					data[catagory][label] = {}
			else:
				if haveLable == False and cell.tag == 'th' and cell.find('font').text.strip() in ['Downstream', 'Upstream', 'Signal Stats (Codewords)']:
					catagory = cell.find('font').text.strip()
					if catagory == 'Signal Stats (Codewords)':
						catagory = 'Stats'
					data[catagory] = {}
				elif haveLable == False and catagory is not None and channels is None and cell.text.strip() == 'Channel ID':
					channels = []
				elif haveLable and channels is not None:
					channels.append(cell.text.strip())
			del cell
			# The first column contains the lable, the remaining column are the data
			haveLable = True
		# Once we are done setting up the channels, the remaining rows are the data
		if channels is not None:
			dataRow = True
			data[catagory]['channels'] = channels

		del row, label, haveLable, channelCount
	del table, catagory, dataRow, channels

# Process data to generate results
results = []
if queryType == 'Downstream':
	# Add Downstream Power Levels to results
	for channel in downChannels:
		value = data['Downstream']['Power Level'][channel]
		value = value.replace(' dBmV', '')
		results.append('DownstreamPower%s:%s' % (channel,value))
		del value, channel
	# Add Downstream Noise Ratio to results
	for channel in downChannels:
		value = data['Downstream']['Signal to Noise Ratio'][channel]
		value = value.replace(' dB', '')
		results.append('DownstreamNoiseRatio%s:%s' % (channel,value))
		del value, channel
elif queryType == 'Upstream':
	# Add Upstream Power Levels to results
	for channel in upChannels:
		value = data['Upstream']['Power Level'][channel]
		value = value.replace(' dBmV', '')
		results.append('UpstreamPower%s:%s' % (channel,value))
		del value, channel
elif queryType == 'Stats':
	# Add Signal Stats to results
	for channel in downChannels:
		results.append('StatsUnerrored%s:%s' % (channel,data['Stats']['Total Unerrored Codewords'][channel]))
		results.append('StatsCorrectable%s:%s' % (channel,data['Stats']['Total Correctable Codewords'][channel]))
		results.append('StatsUncorrectable%s:%s' % (channel,data['Stats']['Total Uncorrectable Codewords'][channel]))
		del channel

# Return results
print ' '.join(results)

# Cleanup
del queryType, usage, results, data, downChannels, upChannels