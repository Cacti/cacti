#!/usr/bin/python3
"""Retrieve data from a Motorola SB6140 Cable Modem.

This script fetches channel and signal statistics and prints
space-separated key:value pairs suitable for monitoring ingestion.

Requires the third-party packages requests and lxml
(python3-requests and python3-lxml on most distributions).
"""

# pylint: disable=invalid-name

import re
import sys
import time

from lxml import html
import requests

USAGE = f"usage: {sys.argv[0]} [Downstream|Upstream|Stats] <hostname>"

# A bare host name, IPv4 or bracketed IPv6 address with an optional port; no
# userinfo, path, query or fragment can be smuggled into the URL.
HOST_RE = re.compile(r'^[A-Za-z0-9.:\[\]-]+$')
# The status page is a few KiB; anything far larger is not the modem.
MAX_BYTES = 1024 * 1024
READ_TIMEOUT = 10
TOTAL_DEADLINE = 20
# Field names and values go straight into the poller output, so only plain
# channel ids and numbers are passed through.
CHANNEL_RE = re.compile(r'^[A-Za-z0-9_]+$')
NUMBER_RE = re.compile(r'^-?[0-9.]+$')


def cell_text(cell):
    """Return the stripped text of a cell, treating an empty cell as ''."""
    return (cell.text or '').strip()


def channel_sort_key(channel):
    """Order channel ids numerically so the output keeps its 1..8 ordering."""
    return (0, int(channel), '') if channel.isdigit() else (1, 0, channel)


def fetch(url):
    """Return the page body, refusing redirects, oversized bodies and stalls."""
    response = requests.get(url, timeout=READ_TIMEOUT, allow_redirects=False, stream=True)
    try:
        response.raise_for_status()
        if response.status_code != 200:
            raise requests.RequestException(f'unexpected HTTP status {response.status_code}')
        deadline = time.monotonic() + TOTAL_DEADLINE
        body = bytearray()
        for chunk in response.iter_content(chunk_size=65536):
            body += chunk
            if len(body) > MAX_BYTES:
                raise ValueError(f'response is larger than {MAX_BYTES} bytes')
            if time.monotonic() > deadline:
                raise ValueError(f'response took longer than {TOTAL_DEADLINE} seconds')
        return bytes(body)
    finally:
        response.close()


if len(sys.argv) != 3:
    print("category and hostname or IP of the modem are required!", file=sys.stderr)
    print(USAGE, file=sys.stderr)
    sys.exit(1)

query_type = sys.argv[1]
if query_type not in ["Downstream", "Upstream", "Stats"]:
    print(USAGE, file=sys.stderr)
    sys.exit(1)

host = sys.argv[2]
if not HOST_RE.fullmatch(host) or host.startswith('-'):
    print(f"invalid modem hostname or IP: {host!r}", file=sys.stderr)
    print(USAGE, file=sys.stderr)
    sys.exit(1)

try:
    tree = html.fromstring(fetch(f"http://{host}/cmSignalData.htm"))
except (requests.RequestException, ValueError) as exc:
    print("Unable to retrieve data from modem!", file=sys.stderr)
    print(exc, file=sys.stderr)
    sys.exit(1)

data = {}
for table in tree.xpath('//table/tbody'):
    category = None
    channels = []
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
                    and cell_text(cell) == 'Channel ID'
                ):
                    channels_ready = True
                    channels = []
                elif have_label and channels_ready:
                    channels.append(cell_text(cell))
            else:
                # actual data rows
                if have_label:
                    if label is not None and channel_count < len(channels):
                        data[category][label][channels[channel_count]] = cell_text(cell)
                        channel_count += 1
                else:
                    label = cell_text(cell)
                    data[category][label] = {}

            # The first column contains the label, the remaining columns are data
            have_label = True

        # Once channels are set, the remaining rows are data rows
        if channels_ready:
            data_row = True
            data[category]['channels'] = channels


def channel_values(category, label, suffix=''):
    """Yield (channel, value) for every channel the modem reported.

    Channel ids come from the modem's own table rather than a fixed list, so a
    modem bonded to different channels does not abort the whole poll. Channel
    ids that are not plain words are skipped, and a reading that is missing or
    not a number is reported as U.
    """
    section = data.get(category, {})
    readings = section.get(label, {})
    channels = [channel for channel in section.get('channels', []) if CHANNEL_RE.fullmatch(channel)]
    for channel in sorted(channels, key=channel_sort_key):
        value = readings.get(channel, '')
        if suffix:
            value = value.replace(suffix, '')
        yield channel, value if NUMBER_RE.fullmatch(value) else 'U'


# Process data to generate results
results = []
if query_type == 'Downstream':
    # Add Downstream Power Levels to results
    for channel, value in channel_values('Downstream', 'Power Level', ' dBmV'):
        results.append(f'DownstreamPower{channel}:{value}')

    # Add Downstream Noise Ratio to results
    for channel, value in channel_values('Downstream', 'Signal to Noise Ratio', ' dB'):
        results.append(f'DownstreamNoiseRatio{channel}:{value}')

elif query_type == 'Upstream':
    # Add Upstream Power Levels to results
    for channel, value in channel_values('Upstream', 'Power Level', ' dBmV'):
        results.append(f'UpstreamPower{channel}:{value}')

elif query_type == 'Stats':
    # Add Signal Stats to results
    unerrored = dict(channel_values('Stats', 'Total Unerrored Codewords'))
    correctable = dict(channel_values('Stats', 'Total Correctable Codewords'))
    uncorrectable = dict(channel_values('Stats', 'Total Uncorrectable Codewords'))
    for channel in unerrored:
        results.append(f"StatsUnerrored{channel}:{unerrored[channel]}")
        results.append(f"StatsCorrectable{channel}:{correctable[channel]}")
        results.append(f"StatsUncorrectable{channel}:{uncorrectable[channel]}")

# Return results
print(' '.join(results))
