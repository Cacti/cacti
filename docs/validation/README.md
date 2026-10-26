# Theme validation

Cacti's theme checks exercise rendered HTML/CSS with populated synthetic
monitoring data. The README shows one representative device inventory;
the guides below contain the complete screenshot sets and reproduction commands.

| Guide | Scope |
| --- | --- |
| [GitHub Actions](github-actions.md) | Checks, matrix fixtures, reports, artifacts, and troubleshooting |
| [Plugin compatibility](plugin-theme-compatibility.md) | Thold, MacTrack, Syslog, Alarm Manager, HMIB, FlowView, Monitor |
| [RTM compatibility](rtm-theme-compatibility.md) | All eighteen source directories inventoried; seventeen available UI surfaces |
| [Cacti](cacti-theme.md) | Core theme rendering and populated screenshots |
| [Carrot](carrot-theme.md) | Core theme rendering and populated screenshots |
| [Dark](dark-theme.md) | Core theme rendering and populated screenshots |
| [Deepness](deepness-theme.md) | Core theme rendering and populated screenshots |
| [Hollyberry](hollyberry-theme.md) | Core theme rendering and populated screenshots |
| [Midwinter](midwinter-theme.md) | Dark and light palettes |
| [Modern](modern-theme.md) | Core theme rendering and populated screenshots |
| [Paw](paw-theme.md) | Core theme rendering and populated screenshots |
| [Raspberry](raspberry-theme.md) | Core theme rendering and populated screenshots |
| [Sunrise](sunrise-theme.md) | Core theme rendering and populated screenshots |
| [Paper Plane](paper-plane-theme.md) | Core theme rendering and populated screenshots |

These are browser component fixtures. They do not test plugin authentication,
backend requests, pollers, scheduler behavior, or live license servers. RTM's
public source omits FusionCharts, so chart rendering and tooltips remain outside
the tested scope. Project Mapping contains demo setup files rather than an
installable UI. The RTM guide records both limitations.

The core gate requires 100% of changed executable CSS lines to be exercised.
That is not whole-project or branch coverage. Plugin comparisons preserve
existing palette deficits in their contrast JSON rather than claiming that all
pre-existing colors meet accessibility thresholds.
