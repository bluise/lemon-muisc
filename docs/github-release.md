# 柠檬音乐 v1.2.13

## 更新

### WAV / APE 格式完善

- **标签编辑**：支持读写 WAV（RIFF INFO + ID3）、APE（APEv2），含封面与歌词保存
- **下载内嵌**：WAV / APE 也可写入封面、歌词
- **播放**：WAV 浏览器直接播放；APE 需本机安装 **ffmpeg**（自动转码为 WAV 后播放）

本版仍为原生独立应用（不依赖 Docker），依赖应用中心 Node.js v22。

## 安装包

- `lemon-music-1.2.13-x86.fpk`
- `lemon-music-1.2.13-arm.fpk`
