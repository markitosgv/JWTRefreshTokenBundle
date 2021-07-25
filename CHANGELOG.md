# Changelog

## Unreleased

- Added `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface` as an interface for extracting the refresh token from the request, implementations provided by this bundle include:
    - `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ChainExtractor` - Calls all registered extractors to find the request token (by default, this extractor is aliased to the interface in the DI container)
    - `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestBodyExtractor` - Decodes a JSON request body and loads the token from it
    - `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\RequestParameterExtractor` - Loads the refresh token by calling `$request->get()`
- Removed the `Gesdinet\JWTRefreshTokenBundle\Request\RequestRefreshToken` class, a `Gesdinet\JWTRefreshTokenBundle\Request\Extractor\ExtractorInterface` implementation should be used instead
- `Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface` now extends `Stringable`, refresh token models now require a `__toString()` method
