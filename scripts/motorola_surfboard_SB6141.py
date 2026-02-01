#!/usr/bin/python3
"""Retrieve data from a Motorola SB6140 Cable Modem.

This script fetches channel and signal statistics and prints
space-separated key:value pairs suitable for monitoring ingestion.
"""

# pylint: disable=invalid-name

import sys

from lxml import html
import requests

USAGE = f"usage: {sys.argv[0]} [Downstream|Upstream|Stats] <hostname>"
DOWN_CHANNELS = ["1", "2", "3", "4", "5", "6", "7", "8"]
UP_CHANNELS = ["5", "6", "7", "8"]

if len(sys.argv) != 3:
    print("category and hostname or IP of the modem are required!")
    print(USAGE)
    sys.exit(1)

query_type = sys.argv[1]
if query_type not in ["Downstream", "Upstream", "Stats"]:
    print(USAGE)
    sys.exit(1)

try:
    page = requests.get(f"http://{sys.argv[2]}/cmSignalData.htm", timeout=10)
    tree = html.fromstring(page.text)
except requests.RequestException as exc:  # pragma: no cover - network error handling
    print("Unable to retrieve data from modem!")
    print(exc)
    sys.exit(1)

data = {}
for table in tree.xpath('//table/tbody'):
    category = None
    channels: list[str] = []
    channels_ready = False
    data_row = False

    for row in table:
        have_label = False
        label = None
        channel_count = 0

        for cell in row:
            if not data_row:
                # header/setup rows
                font = cell.find('font')

                if (
                    not have_label
                    and font is not None
                    and font.text
                    and font.text.strip()
                    in ['Downstream', 'Upstream', 'Signal Stats (Codewords)']
                ):
                    category = font.text.strip()
                    if category == 'Signal Stats (Codewords)':
                        category = 'Stats'
                    data[category] = {}
                elif (
                    not have_label
                    and category is not None
                    and not channels_ready
                    and cell.text
                    and cell.text.strip() == 'Channel ID'
                ):
                    channels_ready = True
                    channels = []
                elif have_label and channels_ready:
                    channels.append(cell.text.strip())
            else:
                # actual data rows
                if have_label:
                    if category is not None and label is not None and channels_ready:
                        data[category][label][channels[channel_count]] = cell.text.strip()
                        channel_count += 1
                else:
                    label = cell.text.strip()
                    data[category][label] = {}

            # The first column contains the label, the remaining columns are data
            have_label = True

        # Once channels are set, the remaining rows are data rows
        if channels_ready:
            data_row = True
            data[category]['channels'] = channels

# Process data to generate results
results = []
if query_type == 'Downstream':
    # Add Downstream Power Levels to results
    for channel in DOWN_CHANNELS:
        value = data['Downstream']['Power Level'][channel]
        value = value.replace(' dBmV', '')
        results.append(f'DownstreamPower{channel}:{value}')

    # Add Downstream Noise Ratio to results
    for channel in DOWN_CHANNELS:
        value = data['Downstream']['Signal to Noise Ratio'][channel]
        value = value.replace(' dB', '')
        results.append(f'DownstreamNoiseRatio{channel}:{value}')

elif query_type == 'Upstream':
    # Add Upstream Power Levels to results
    for channel in UP_CHANNELS:
        value = data['Upstream']['Power Level'][channel]
        value = value.replace(' dBmV', '')
        results.append(f'UpstreamPower{channel}:{value}')

elif query_type == 'Stats':
    # Add Signal Stats to results
    for channel in DOWN_CHANNELS:
        results.append(
            f"StatsUnerrored{channel}:{data['Stats']['Total Unerrored Codewords'][channel]}"
        )
        results.append(
            f"StatsCorrectable{channel}:{data['Stats']['Total Correctable Codewords'][channel]}"
        )
        results.append(
            f"StatsUncorrectable{channel}:{data['Stats']['Total Uncorrectable Codewords'][channel]}"
        )

# Return results
print(' '.join(results))
