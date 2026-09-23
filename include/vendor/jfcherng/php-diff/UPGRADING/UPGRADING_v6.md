## Upgrading to v6


### For Simple Users

If you only use the `DiffHelper` and built-in `Renderer`s,
there is no breaking change for you so you do not have to do anything.


### Breaking Changes for Normal Users

- The `Diff` class has been renamed as `Differ`.
  It should be relatively easy to adapt to this by changing the class name.

- The term `template` has been renamed as `renderer`. Some examples are:

  - Method `DiffHelper::getRenderersInfo()`
  - Method `DiffHelper::getAvailableRenderers()`
  - Constant `RendererConstant::RENDERER_TYPES`
  - Constant `AbstractRenderer::IS_TEXT_RENDERER`

- Now a `Renderer` has a `render()` method, but a `Differ` does not.
  (because it makes more sense saying a renderer would render something)
  If you use those classes by yourself, it should be written like below.

  ```php
  use Jfcherng\Diff\Differ;
  use Jfcherng\Diff\Factory\RendererFactory;
  
  $differ = new Differ(explode("\n", $old), explode("\n", $new), $diffOptions);
  $renderer = RendererFactory::make($rendererName, $rendererOptions);
  $result = $renderer->render($differ); // <-- this line has been changed
  ```


### Breaking Changes for Customized Renderer Developers

- Remove the deprecated `AbstractRenderer::getIdenticalResult()` and
  add `RendererInterface::getResultForIdenticals()`. The returned value will be
  directly used before actually starting to calculate diff if we find that the
  two strings are the same. `AbstractRenderer::getResultForIdenticals()`
  returns an empty string by default.

- Now a `Renderer` should implement `protected function renderWorker(Differ $differ): string`
  rather than the previous `public function render(): string`. Note that
  `$this->diff` no longer works in `Renderer`s as it is now injected as a
  parameter to `Renderer::renderWorker()`.
