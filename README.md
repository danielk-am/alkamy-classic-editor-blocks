# alkamy-classic-editor-blocks
Edit WordPress posts and pages with blocks inside the classic editor.

## Build the installable ZIP

Run from the repository root. The archive includes the plugin, assets, WordPress readme and license.

```sh
mkdir -p build/alkamy-classic-editor-blocks
cp -R alkamy-classic-editor-blocks.php assets readme.txt LICENSE build/alkamy-classic-editor-blocks/
(cd build && zip -qrX alkamy-classic-editor-blocks.zip alkamy-classic-editor-blocks)
```
