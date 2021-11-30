#!/usr/bin/env python
# -*- coding: utf-8 -*-
import sys                      # System bindings
import cv2                      # OpenCV bindings
import numpy as np
from urllib import request
from collections import Counter

"""
获取项目组配置的refer
"""
def get_refer(project_id):
    tup = ()
    exits = tup.__contains__(project_id)
    if exits:
        info = {}
        return info[project_id]
    return '123'

def url_to_image(val):
    # download the image, convert it to a NumPy array, and then read
	# it into OpenCV format
    rp = request.Request(val[0])
    if val[1]:
        refer = get_refer(val[1])
        if refer:
            rp.add_header('Referer', refer)

    resp = request.urlopen(rp)
    image = np.asarray(bytearray(resp.read()), dtype="uint8")
    image = cv2.imdecode(image, cv2.IMREAD_COLOR)
    return image

class BackgroundColorDetector():
    def __init__(self, imageLoc):
        self.img = imageLoc
        self.manual_count = {}
        self.w, self.h, self.channels = self.img.shape
        self.total_pixels = self.w*self.h

    def count(self):
        for y in range(0, self.h):
            for x in range(0, self.w):
                RGB = (self.img[x, y, 2], self.img[x, y, 1], self.img[x, y, 0])
                if RGB in self.manual_count:
                    self.manual_count[RGB] += 1
                else:
                    self.manual_count[RGB] = 1

    def average_colour(self):
        red = 0
        green = 0
        blue = 0
        sample = 10
        for top in range(0, sample):
            red += self.number_counter[top][0][0]
            green += self.number_counter[top][0][1]
            blue += self.number_counter[top][0][2]

        average_red = red / sample
        average_green = green / sample
        average_blue = blue / sample
        print("Average RGB for top ten is: (", average_red,
              ", ", average_green, ", ", average_blue, ")")

    def twenty_most_common(self):
        self.count()
        self.number_counter = Counter(self.manual_count).most_common(20)
        # for rgb, value in self.number_counter:
        #     print(rgb, value, ((float(value)/self.total_pixels)*100))

    def detect(self):
        self.twenty_most_common()
        self.percentage_of_first = (
            float(self.number_counter[0][1])/self.total_pixels)
        # print(self.percentage_of_first)
        if self.percentage_of_first > 0.5:
            # print("Background color is ", self.number_counter[0][0])
            return 0
        else:
            # self.average_colour()
            return 1


if __name__ == "__main__":
    if (len(sys.argv) != 2):                        # Checks if image was given as cli argument
        print("error: syntax is ")
    else:
        imgdict = []
        img = url_to_image(imgdict)
        BackgroundColor = BackgroundColorDetector(img)
        ret = BackgroundColor.detect()
        print(ret)